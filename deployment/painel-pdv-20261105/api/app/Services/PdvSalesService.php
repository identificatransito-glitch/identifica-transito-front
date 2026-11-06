<?php
namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Models\Pdv;
use App\Models\TagPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** One attribution per order: preferred pickup point, otherwise customer-origin PDV. */
class PdvSalesService
{
    public function attributedTo(Pdv $point): Builder
    {
        $admins = $point->users()->where('type', UserTypeEnum::Admin->value)->pluck('id');
        return TagPurchase::query()->where(function ($scope) use ($point, $admins) {
            $scope->where(function ($pickup) use ($point) {
                $pickup->whereNull('delivery')->where(function ($id) use ($point) {
                    $id->where('pickup_point->id', (int) $point->id)
                        ->orWhere('pickup_point->id', (string) $point->id);
                });
            })->orWhere(function ($origin) use ($admins) {
                $origin->where(function ($withoutPoint) {
                    $withoutPoint->whereNotNull('delivery')->orWhereNull('pickup_point->id')
                        ->orWhere('pickup_point->id', 0)->orWhere('pickup_point->id', '0');
                })->whereHas('user', fn ($user) => $user->whereIn('user_id_admin_pdv', $admins));
            });
        });
    }

    public function operationalFor(User $admin): Builder
    {
        return TagPurchase::query()->where(function ($scope) use ($admin) {
            $scope->whereHas('user', fn ($user) => $user->where('user_id_admin_pdv', $admin->id));
            if ((int) $admin->pdv_id > 0) {
                $scope->orWhere(function ($pickup) use ($admin) {
                    $pickup->whereNull('delivery')->where(function ($id) use ($admin) {
                        $id->where('pickup_point->id', (int) $admin->pdv_id)
                            ->orWhere('pickup_point->id', (string) $admin->pdv_id);
                    });
                });
            }
        });
    }

    private function approved(Pdv $point, $from = null, $to = null): Builder
    {
        $sales=$this->attributedTo($point)->where('mp_payment_status','approved');
        if($from !== null) $sales->where('created_at','>=',$from);
        if($to !== null) $sales->where('created_at','<=',$to);
        return $sales;
    }

    public function charts(Pdv $point, $from = null, $to = null): array
    {
        $sales=$this->approved($point,$from,$to);
        $tags=(clone $sales)->selectRaw('tag_id, SUM(qty) as quantity')->groupBy('tag_id')->with('tag:id,name,slug')->get()
            ->map(fn ($sale)=>['tag_name'=>$sale->tag?->name ?? 'Não informado', 'tag_slug'=>$sale->tag?->slug ?? 'sem-classificacao', 'qty'=>(int)$sale->quantity])->values()->all();
        $days=(clone $sales)->selectRaw('DATE(created_at) as report_date, SUM(price * qty) as base_total, SUM(price_total) as received_total')
            ->groupByRaw('DATE(created_at)')->orderBy('report_date')->get();
        $base=0.0;$received=0.0;$commissionHistory=[];$revenueHistory=[];
        foreach($days as $day){
            $base=round($base+(float)$day->base_total,2);$received=round($received+(float)$day->received_total,2);
            $commissionHistory[]=['date'=>$day->report_date, 'balance'=>$point->commissionAmount($base)];
            $revenueHistory[]=['date'=>$day->report_date, 'balance'=>$received];
        }
        return ['tag_sales'=>$tags, 'balance_history'=>$commissionHistory, 'revenue_history'=>$revenueHistory];
    }

    public function totals(Pdv $point, $from = null, $to = null): array
    {
        $sales = $this->approved($point,$from,$to);
        // price is the historical unit price saved before applying the coupon. Shipping excluded.
        $base = round((float) (clone $sales)->sum(\Illuminate\Support\Facades\DB::raw('price * qty')), 2);
        return [
            'total_sales_qty' => (int) (clone $sales)->sum('qty'),
            'total_sales_amount' => round((float) (clone $sales)->sum('price_total'), 2),
            'commission_base_amount' => $base,
            'commission_amount' => $point->commissionAmount($base),
        ];
    }
}
