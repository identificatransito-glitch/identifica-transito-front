<?php

namespace App\Services;

use App\Enums\PdvCommissionTypeEnum;
use App\Enums\PickupShipmentStatusEnum;
use App\Enums\UserTypeEnum;
use App\Enums\VehicleTypeEnum;
use App\Jobs\SendShipmentStatusUpdatedMailJob;
use App\Models\Shipment;
use App\Models\TagPurchase;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AdminPdvService
{
    private function consultableUsers(User $adminPdv): \Illuminate\Database\Eloquent\Builder
    {
        $orders=(new PdvSalesService)->operationalFor($adminPdv)->select('user_id');
        return User::query()->where('type', UserTypeEnum::Client->value)->where(function ($q) use ($adminPdv,$orders) {
            $q->where('user_id_admin_pdv',$adminPdv->id)->orWhereIn('id',$orders);
        });
    }

    public function userTotals(User $adminPdv): array
    {
        return ['active'=>$this->consultableUsers($adminPdv)->count(),
            'pdv'=>$this->consultableUsers($adminPdv)->whereNotNull('pdv_id')->count(),
            'deleted'=>$this->consultableUsers($adminPdv)->withTrashed()->whereNotNull('deleted_at')->count()];
    }

    public function vehicleTotals(User $adminPdv): array
    {
        $registered = Vehicle::query()
            ->whereNull('deleted_at')
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
            ->count();

        $deleted = Vehicle::query()
            ->whereNotNull('deleted_at')
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
            ->count();

        $totalsByType = Vehicle::query()
            ->selectRaw('type, COUNT(*) as total')
            ->whereNull('deleted_at')
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
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

    public function users(User $adminPdv, ?string $search = null): LengthAwarePaginator
    {
        $query=$this->consultableUsers($adminPdv)->withCount(['vehicles','tagPurchases'])->orderByDesc('created_at');
        if($search !== null && $search !== '') $query->where(function ($q) use ($search,$adminPdv) {
            $q->where('name','like','%'.$search.'%')->orWhere(function ($own) use ($search,$adminPdv) {
                $own->where('user_id_admin_pdv',$adminPdv->id)->where('email','like','%'.$search.'%');
            });
        });
        $page=$query->paginate(10,['id','name','email','phone','type','created_at','user_id_admin_pdv']);
        $page->getCollection()->transform(function ($user) use ($adminPdv) {
            if((int)$user->user_id_admin_pdv !== (int)$adminPdv->id) {
                $user->email=null;$user->phone=null;
                $orders=(new PdvSalesService)->operationalFor($adminPdv)->where('user_id',$user->id);
                $user->vehicles_count=(clone $orders)->distinct()->count('vehicle_id');
                $user->tag_purchases_count=(clone $orders)->count();
            }
            return $user;
        });
        return $page;
    }

    public function vehicles(User $adminPdv, ?string $search = null): LengthAwarePaginator
    {
        $query = Vehicle::query()
            ->with([
                'user:id,name,user_id_admin_pdv',
                'tagPurchases' => static function ($query) {
                    $query->orderByDesc('id');
                },
            ])
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
            ->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($subQuery) use ($search) {
                        $subQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhere('plate', 'like', '%' . $search . '%')
                    ->orWhere('register_state', 'like', '%' . $search . '%')
                    ->orWhere('register_city', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(10);
    }

    public function orders(User $adminPdv, ?string $search = null): LengthAwarePaginator
    {
        $query = TagPurchase::query()
            ->with([
                'shipment',
                'tag:id,name,slug',
                'user:id,name,user_id_admin_pdv',
                'vehicle:id,name,plate,color,year',
            ])
            ->where(function ($scope) use ($adminPdv) {
                $scope->whereHas('user', function ($user) use ($adminPdv) {
                    $user->where('user_id_admin_pdv', $adminPdv->id);
                });
                if ((int)$adminPdv->pdv_id > 0) {
                    $scope->orWhere(function ($pickup) use ($adminPdv) {
                        $pickup->whereNull('delivery')->where(function ($point) use ($adminPdv) {
                            $point->where('pickup_point->id', (int)$adminPdv->pdv_id)
                                ->orWhere('pickup_point->id', (string)$adminPdv->pdv_id);
                        });
                    });
                }
            })
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

    public function updateOrderStatus(User $adminPdv, int $orderId, array $data): TagPurchase
    {
        $order = TagPurchase::query()
            ->whereKey($orderId)
            ->where(function ($scope) use ($adminPdv) {
                $scope->whereHas('user', function ($user) use ($adminPdv) {
                    $user->where('user_id_admin_pdv', $adminPdv->id);
                });
                if ((int)$adminPdv->pdv_id > 0) {
                    $scope->orWhere(function ($pickup) use ($adminPdv) {
                        $pickup->whereNull('delivery')->where(function ($point) use ($adminPdv) {
                            $point->where('pickup_point->id', (int)$adminPdv->pdv_id)
                                ->orWhere('pickup_point->id', (string)$adminPdv->pdv_id);
                        });
                    });
                }
            })
            ->firstOrFail();

        if ($order->pickup_point !== null && $order->delivery === null) {
            if (isset($data['status']) && (int)$data['status'] === 4) {
                throw ValidationException::withMessages(['status'=>'Confirme a retirada pelo QR do cliente para conferir o pagamento e registrar o ponto da entrega.']);
            }
            if ($order->shipment && (int)$order->shipment->status === 4) {
                throw ValidationException::withMessages(['status'=>'A retirada já foi confirmada. O registro não pode ser reaberto por esta ação.']);
            }
        }

        $shipment = Shipment::query()
            ->where('tag_purchase_id', $order->id)
            ->first();

        if ($shipment) {
            $payload = [];
            $hasStatus = array_key_exists('status', $data);

            if ($hasStatus) {
                $status = (int) $data['status'];

                if (mb_strtolower((string) $shipment->carrier) === 'retirada' && !in_array($status, PickupShipmentStatusEnum::values(), true)) {
                    throw ValidationException::withMessages([
                        'status' => ['Para retirada, use apenas os status 0 (aguardando), 1 (disponivel para retirada) ou 4 (retirado).'],
                    ]);
                }

                $payload['status'] = $status;
                $payload['delivered_at'] = $status === 4 ? now() : null;
            }

            if (array_key_exists('tracking_code', $data)) {
                $payload['tracking_code'] = $data['tracking_code'];
            }

            if ($payload !== []) {
                $shipment->update($payload);
            }

            if ($hasStatus) {
                SendShipmentStatusUpdatedMailJob::dispatch((int) $shipment->id);
            }
        }

        return $order->fresh(['shipment', 'tag:id,name,slug', 'user:id,name', 'vehicle:id,name,plate,color,year']);
    }

    public function dashboard(User $adminPdv, string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        $reports = new PdvSalesService;
        $periodOrders = $reports->operationalFor($adminPdv)->whereBetween('created_at', [$from, $to]);
        $customerScope = function ($query) use ($adminPdv, $periodOrders) {
            $query->where('user_id_admin_pdv', $adminPdv->id)
                ->orWhereIn('id', (clone $periodOrders)->select('user_id'));
        };
        $totalUsers = User::query()->where('type', UserTypeEnum::Client->value)
            ->where($customerScope)->whereBetween('created_at', [$from, $to])->count();
        $totalVehicles = Vehicle::query()->where(function ($query) use ($adminPdv, $periodOrders) {
            $query->whereHas('user', fn ($user) => $user->where('user_id_admin_pdv', $adminPdv->id))
                ->orWhereIn('id', (clone $periodOrders)->select('vehicle_id'));
        })->whereBetween('created_at', [$from, $to])->count();
        $totalOrders = (clone $periodOrders)->distinct('mp_payment_id')->count('mp_payment_id');
        $financial = $adminPdv->pdv ? $reports->totals($adminPdv->pdv, $from, $to)
            : ['total_sales_qty'=>0, 'total_sales_amount'=>0, 'commission_base_amount'=>0, 'commission_amount'=>0];
        $totalRevenue = $financial['total_sales_amount'];
        $commissionAmount = $financial['commission_amount'];
        $latestUsers = User::query()->where('type', UserTypeEnum::Client->value)
            ->where($customerScope)->whereBetween('created_at', [$from, $to])->orderByDesc('created_at')->limit(10)
            ->get(['id', 'name', 'email', 'phone', 'type', 'user_id_admin_pdv', 'created_at'])
            ->map(function ($user) use ($adminPdv) {
                // Pickup orders already expose the customer's name, not their contact details.
                if ((int) $user->user_id_admin_pdv !== (int) $adminPdv->id) { $user->email = null; $user->phone = null; }
                return $user;
            });

        $latestOrderIds = (clone $periodOrders)->selectRaw('MAX(id) as id')
            ->groupBy('mp_payment_id')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit(10)
            ->pluck('id');

        $latestOrders = TagPurchase::query()
            ->with('vehicle:id,plate,name,color,year')
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
                'status',
                'created_at',
            ]);

        return [
            'totals' => [
                'users' => $totalUsers,
                'vehicles' => $totalVehicles,
                'orders' => $totalOrders,
                'revenue' => round((float) $totalRevenue, 2),
                'commission_amount' => $commissionAmount,
                'commission_base_amount' => $financial['commission_base_amount'],
                'sold_qty' => $financial['total_sales_qty'],
            ],
            'latest_users' => $latestUsers,
            'latest_orders' => $latestOrders,
        ];
    }

    public function findUser(User $adminPdv, int $id): User
    {
        return User::query()
            ->whereKey($id)
            ->where('type', UserTypeEnum::Client->value)
            ->where('user_id_admin_pdv', $adminPdv->id)
            ->firstOrFail();
    }

    public function createUser(User $adminPdv, array $data): User
    {
        throw ValidationException::withMessages(['user'=>'O PDV possui acesso somente de consulta aos usuários. Alterações são feitas pelo cliente ou pelo Super Admin.']);
    }

    public function updateUser(User $adminPdv, int $id, array $data): User
    {
        throw ValidationException::withMessages(['user'=>'O PDV possui acesso somente de consulta aos usuários. Alterações são feitas pelo cliente ou pelo Super Admin.']);
    }

    public function deleteUser(User $adminPdv, int $id): void
    {
        throw ValidationException::withMessages(['user'=>'O PDV possui acesso somente de consulta aos usuários. Alterações são feitas pelo cliente ou pelo Super Admin.']);
    }

    public function deleteVehicle(User $adminPdv, int $id): void
    {
        $vehicle = Vehicle::query()
            ->whereKey($id)
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
            ->firstOrFail();

        $vehicle->delete();
    }
}
