<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Enums\VehicleTypeEnum;
use App\Enums\PickupShipmentStatusEnum;
use App\Enums\PdvCommissionTypeEnum;
use App\Jobs\CreateDefaultUserSettingsJob;
use App\Jobs\SendShipmentStatusUpdatedMailJob;
use App\Models\DiscountCoupon;
use App\Models\Faq;
use App\Models\Shipment;
use App\Models\Tag;
use App\Models\TagPurchase;
use App\Models\User;
use App\Models\Pdv;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminService
{
    public function userTotals(): array
    {
        $active = User::query()
            ->where('type', UserTypeEnum::Client->value)
            ->count();

        $pdv = User::query()
            ->where('type', UserTypeEnum::Client->value)
            ->whereNotNull('pdv_id')
            ->count();

        $deleted = User::withTrashed()
            ->where('type', UserTypeEnum::Client->value)
            ->whereNotNull('deleted_at')
            ->count();

        return [
            'active'  => $active,
            'pdv'     => $pdv,
            'deleted' => $deleted,
        ];
    }

    public function vehicleTotals(): array
    {
        $registered = Vehicle::query()
            ->whereNull('deleted_at')
            ->count();

        $deleted = Vehicle::query()
            ->whereNotNull('deleted_at')
            ->count();

        $totalsByType = Vehicle::query()
            ->selectRaw('type, COUNT(*) as total')
            ->whereNull('deleted_at')
            ->groupBy('type')
            ->pluck('total', 'type');

        $byType = collect(VehicleTypeEnum::cases())
            ->map(static fn(VehicleTypeEnum $type): array => [
                'id' => $type->value,
                'label' => $type->label(),
                'total' => (int) ($totalsByType[$type->value] ?? 0),
            ])
            ->values()
            ->all();

        return [
            'registered' => $registered,
            'by_type' => $byType,
            'deleted' => $deleted,
        ];
    }

    public function users(?string $search = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = User::query()
            ->withCount(['vehicles', 'tagPurchases'])
            ->where('type', UserTypeEnum::Client->value)
            ->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(10);
    }

    public function vehicles(?string $search = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Vehicle::query()
            ->with([
                'user:id,name',
                'tagPurchases' => static function ($query) {
                    $query->orderByDesc('id');
                },
            ])
            ->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhere('plate', 'like', '%' . $search . '%')
                    ->orWhere('register_state', 'like', '%' . $search . '%')
                    ->orWhere('register_city', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(10);
    }

    public function tags(?string $search = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Tag::query()
            ->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('slug', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('color_name', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(10);
    }

    public function discountCoupons(?string $search = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = DiscountCoupon::query()
            ->withCount(['tagPurchases as used_qty'])
            ->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', '%' . $search . '%')
                    ->orWhere('type', 'like', '%' . $search . '%')
                    ->orWhere('value', 'like', '%' . $search . '%')
                    ->orWhere('qty', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(10);
    }

    public function orders(?string $search = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = TagPurchase::query()
            ->with([
                'shipment',
                'tag:id,name,slug',
                'user:id,name',
                'vehicle:id,name,plate,color,year',
            ])
            ->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('mp_payment_id', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('vehicle', function ($vehicleQuery) use ($search) {
                        $vehicleQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('plate', 'like', '%' . $search . '%');
                    });
            });
        }

        return $query->paginate(10);
    }

    public function findTag(int $id): Tag
    {
        return Tag::query()->findOrFail($id);
    }

    public function findDiscountCoupon(int $id): DiscountCoupon
    {
        return DiscountCoupon::query()
            ->withCount(['tagPurchases as used_qty'])
            ->findOrFail($id);
    }

    public function createTag(array $data): Tag
    {
        return Tag::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'color_name' => $data['color_name'],
            'price' => $data['price'],
        ]);
    }

    public function createDiscountCoupon(array $data): DiscountCoupon
    {
        return DiscountCoupon::query()->create([
            'code' => $data['code'],
            'type' => $data['type'],
            'value' => $data['value'],
            'qty' => $data['qty'] ?? null,
            'expiration_date' => $data['expiration_date'] ?? null,
        ]);
    }

    public function updateTag(int $id, array $data): Tag
    {
        $tag = Tag::query()->findOrFail($id);

        $fields = [];

        if (array_key_exists('name', $data)) {
            $fields['name'] = $data['name'];
            $fields['slug'] = $data['slug'];
        }

        foreach (['description', 'color_name', 'price'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if ($fields !== []) {
            $tag->update($fields);
        }

        return $tag->fresh();
    }

    public function updateDiscountCoupon(int $id, array $data): DiscountCoupon
    {
        $coupon = DiscountCoupon::query()->findOrFail($id);

        $fields = [];

        foreach (['code', 'type', 'value', 'qty', 'expiration_date'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if ($fields !== []) {
            $coupon->update($fields);
        }

        return $coupon->fresh();
    }

    public function deleteTag(int $id): void
    {
        $tag = Tag::query()->findOrFail($id);

        $tag->delete();
    }

    public function deleteDiscountCoupon(int $id): void
    {
        $coupon = DiscountCoupon::query()->findOrFail($id);

        $coupon->delete();
    }

    public function deleteVehicle(int $id): void
    {
        $vehicle = Vehicle::query()->findOrFail($id);

        $vehicle->delete();
    }

    public function updateOrderShipmentStatus(int $orderId, array $data): Shipment
    {
        $shipment = Shipment::query()
            ->where('tag_purchase_id', $orderId)
            ->firstOrFail();

        $status = (int) $data['status'];

        if (mb_strtolower((string) $shipment->carrier) === 'retirada' && !in_array($status, PickupShipmentStatusEnum::values(), true)) {
            throw ValidationException::withMessages([
                'status' => ['Para retirada, use apenas os status 0 (aguardando), 1 (disponivel para retirada) ou 4 (retirado).'],
            ]);
        }

        $payload = [
            'status' => $status,
            'delivered_at' => $status === 4 ? now() : null,
        ];

        if (array_key_exists('tracking_code', $data)) {
            $payload['tracking_code'] = $data['tracking_code'];
        }

        $shipment->update($payload);

        SendShipmentStatusUpdatedMailJob::dispatch((int) $shipment->id);

        return $shipment->fresh();
    }

    public function updateOrderStatus(int $orderId, int $status): TagPurchase
    {
        $order = TagPurchase::query()->findOrFail($orderId);

        $order->update([
            'status' => $status,
            'mp_payment_status' => $this->resolveMpPaymentStatus($status),
        ]);

        return $order->fresh();
    }

    private function resolveMpPaymentStatus(int $status): string
    {
        return match ($status) {
            1 => 'approved',
            2 => 'cancelled',
            3 => 'rejected',
            default => 'pending',
        };
    }

    public function dashboard(string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to   = Carbon::parse($dateTo)->endOfDay();

        $totalUsers = User::query()
            ->where('type', UserTypeEnum::Client->value)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $totalVehicles = Vehicle::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $totalOrders = TagPurchase::query()
            ->whereBetween('created_at', [$from, $to])
            ->distinct('mp_payment_id')
            ->count('mp_payment_id');

        $totalRevenue = TagPurchase::query()
            ->where('mp_payment_status', 'approved')
            ->whereBetween('created_at', [$from, $to])
            ->sum('price_total');

        $latestUsers = User::query()
            ->where('type', UserTypeEnum::Client->value)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone', 'type', 'created_at']);

        $latestOrderIds = TagPurchase::query()
            ->selectRaw('MAX(id) as id')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('mp_payment_id')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(4)
            ->pluck('id');

        $latestOrders = TagPurchase::query()
            ->with(['vehicle:id,plate,name,color,year', 'tag:id,name,slug', 'shipment:id,tag_purchase_id,status'])
            ->whereIn('id', $latestOrderIds)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'user_id',
                'vehicle_id',
                'tag_id',
                'mp_payment_id',
                'mp_payment_status',
                'payment_method',
                'qty',
                'price',
                'price_total',
                'shipping_price',
                'delivery',
                'pickup_point',
                'coupon_info',
                'mp_payment_status_detail',
                'status',
                'created_at',
            ]);

        return [
            'totals' => [
                'users'    => $totalUsers,
                'vehicles' => $totalVehicles,
                'orders'   => $totalOrders,
                'revenue'  => round((float) $totalRevenue, 2),
            ],
            'latest_users'  => $latestUsers,
            'latest_orders' => $latestOrders,
        ];
    }

    // ── Repasse de PDV ───────────────────────────────────────────────────────

    public function pdvCommissions(?string $dateFrom, ?string $dateTo, ?int $adminId, ?string $search, int $perPage = 200): \Illuminate\Pagination\LengthAwarePaginator
    {
        $from = $dateFrom !== null ? Carbon::parse($dateFrom)->startOfDay() : null;
        $to   = $dateTo !== null ? Carbon::parse($dateTo)->endOfDay() : null;

        // One row per point; pickup preference has priority over customer origin.
        $query = Pdv::query()->orderBy('name');
        if ($adminId !== null) {
            $query->whereHas('users', fn ($q) => $q->whereKey($adminId)->where('type', UserTypeEnum::Admin->value));
        }
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('users', fn ($u) => $u->where('type', UserTypeEnum::Admin->value)
                        ->where(fn ($a) => $a->where('name', 'like', '%' . $search . '%')->orWhere('email', 'like', '%' . $search . '%')));
            });
        }
        $points = $query->paginate($perPage);
        $points->getCollection()->transform(function (Pdv $point) use ($from, $to) {
            $admins = $point->users()->where('type', UserTypeEnum::Admin->value)->pluck('id');
            $totals = (new PdvSalesService)->totals($point, $from, $to);
            return [
                'pdv_id' => $point->id,
                'pdv_name' => $point->name,
                'staff_count' => $admins->count(),
                'commission_type' => $point->commission_percentage === null ? null : 2,
                'commission_type_label' => $point->commission_percentage === null ? null : 'Percentual do PDV',
                'commission_value' => $point->commission_percentage,
                'commission_percentage' => $point->commission_percentage,
                'total_sales_qty' => $totals['total_sales_qty'],
                'total_sales_amount' => $totals['total_sales_amount'],
                'commission_base_amount' => $totals['commission_base_amount'],
                'commission_amount' => $totals['commission_amount'],
            ];
        });
        return $points;
    }

    // ── CRUD de usuários ──────────────────────────────────────────────────────

    public function findUser(int $id): User
    {
        return User::query()->findOrFail($id);
    }

    public function createUser(array $data, User $authenticatedUser): User
    {
        if ((int)($data['type']??3)!==3) throw ValidationException::withMessages(['type'=>'Cadastre o administrador diretamente em Pontos de Venda.']);
        $user = User::query()->create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'cpf'               => $data['cpf'] ?? null,
            'birth_date'        => $data['birth_date'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'type'              => UserTypeEnum::Client->value,
            'pdv_id'            => $data['pdv_id'] ?? null,
            // 'user_id_admin_pdv' => $data['user_id_admin_pdv'] ?? null,
        ]);

        CreateDefaultUserSettingsJob::dispatch($authenticatedUser->id);

        return $user;
    }

    public function updateUser(int $id, array $data): User
    {
        if (array_key_exists('type',$data) && (int)$data['type']!==3) throw ValidationException::withMessages(['type'=>'Gerencie administradores em Pontos de Venda.']);
        $user = User::query()->findOrFail($id);

        $this->ensureClient($user);

        $fields = array_filter([
            'name'              => $data['name'] ?? null,
            'email'             => $data['email'] ?? null,
            'cpf'               => $data['cpf'] ?? null,
            'birth_date'        => $data['birth_date'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'type'              => $data['type'] ?? null,
        ], fn($v) => $v !== null);

        // Nullable fields must be handled explicitly
        foreach (['pdv_id', 'user_id_admin_pdv'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if (!empty($data['password'])) {
            $fields['password'] = Hash::make($data['password']);
        }

        $user->update($fields);

        return $user->fresh();
    }

    public function deleteUser(int $id): void
    {
        $user = User::query()->findOrFail($id);

        $this->ensureClient($user);

        $user->delete();
    }

    private function ensureClient(User $user): void
    {
        if ($user->type !== UserTypeEnum::Client->value) {
            throw new AuthorizationException('Gerencie administradores em Pontos de Venda. A conta Super Admin é preservada.');
        }
    }

    // ── CRUD de FAQs ──────────────────────────────────────────────────────────

    public function faqs(?string $search = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Faq::query()->orderByRaw('`order` IS NULL ASC, `order` ASC')->orderBy('id');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', '%' . $search . '%')
                    ->orWhere('answer', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(10);
    }

    public function findFaq(int $id): Faq
    {
        return Faq::query()->findOrFail($id);
    }

    public function createFaq(array $data): Faq
    {
        return Faq::query()->create([
            'question' => $data['question'],
            'answer'   => $data['answer'],
            'active'   => $data['active'] ?? true,
            'order'    => $data['order'] ?? null,
        ]);
    }

    public function updateFaq(int $id, array $data): Faq
    {
        $faq = Faq::query()->findOrFail($id);

        $fields = [];

        foreach (['question', 'answer', 'active', 'order'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if ($fields !== []) {
            $faq->update($fields);
        }

        return $faq->fresh();
    }

    public function deleteFaq(int $id): void
    {
        $faq = Faq::query()->findOrFail($id);

        $faq->delete();
    }

    public function reorderFaq(int $id, int $newOrder): Faq
    {
        $faq      = Faq::query()->findOrFail($id);
        $oldOrder = $faq->order; // mantém null se não tiver ordem definida

        if ($oldOrder !== null && (int) $oldOrder === $newOrder) {
            return $faq;
        }

        if ($oldOrder === null) {
            // Item sem ordem definida: abre espaço na posição destino
            Faq::query()
                ->where('id', '!=', $id)
                ->where('order', '>=', $newOrder)
                ->increment('order');
        } elseif ($newOrder > (int) $oldOrder) {
            // Movendo para baixo: registros entre (old+1) e new sobem uma posição
            Faq::query()
                ->where('id', '!=', $id)
                ->whereBetween('order', [(int) $oldOrder + 1, $newOrder])
                ->decrement('order');
        } else {
            // Movendo para cima: registros entre new e (old-1) descem uma posição
            Faq::query()
                ->where('id', '!=', $id)
                ->whereBetween('order', [$newOrder, (int) $oldOrder - 1])
                ->increment('order');
        }

        $faq->update(['order' => $newOrder]);

        return $faq->fresh();
    }
}
