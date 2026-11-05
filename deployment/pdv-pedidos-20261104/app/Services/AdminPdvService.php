<?php

namespace App\Services;

use App\Enums\PdvCommissionTypeEnum;
use App\Enums\PickupShipmentStatusEnum;
use App\Enums\UserTypeEnum;
use App\Enums\VehicleTypeEnum;
use App\Jobs\CreateDefaultUserSettingsJob;
use App\Jobs\SendShipmentStatusUpdatedMailJob;
use App\Models\Shipment;
use App\Models\TagPurchase;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminPdvService
{
    public function userTotals(User $adminPdv): array
    {
        $active = User::query()
            ->where('type', UserTypeEnum::Client->value)
            ->where('user_id_admin_pdv', $adminPdv->id)
            ->count();

        $pdv = User::query()
            ->where('type', UserTypeEnum::Client->value)
            ->where('user_id_admin_pdv', $adminPdv->id)
            ->whereNotNull('pdv_id')
            ->count();

        $deleted = User::withTrashed()
            ->where('type', UserTypeEnum::Client->value)
            ->where('user_id_admin_pdv', $adminPdv->id)
            ->whereNotNull('deleted_at')
            ->count();

        return [
            'active' => $active,
            'pdv' => $pdv,
            'deleted' => $deleted,
        ];
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
        $query = User::query()
            ->withCount(['vehicles', 'tagPurchases'])
            ->where('type', UserTypeEnum::Client->value)
            ->where('user_id_admin_pdv', $adminPdv->id)
            ->orderByDesc('created_at');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate(10);
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

        $totalUsers = User::query()
            ->where('type', UserTypeEnum::Client->value)
            ->where('user_id_admin_pdv', $adminPdv->id)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $totalVehicles = Vehicle::query()
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $totalOrders = TagPurchase::query()
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
            ->whereBetween('created_at', [$from, $to])
            ->distinct('mp_payment_id')
            ->count('mp_payment_id');

        $totalRevenue = TagPurchase::query()
            ->where('mp_payment_status', 'approved')
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
            ->whereBetween('created_at', [$from, $to])
            ->sum('price_total');

        $totalSoldQty = (int) TagPurchase::query()
            ->where('mp_payment_status', 'approved')
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
            ->whereBetween('created_at', [$from, $to])
            ->sum('qty');

        $commissionAmount = $adminPdv->pdv?->commissionAmount(round((float) $totalRevenue, 2)) ?? 0.0;

        $latestUsers = User::query()
            ->where('type', UserTypeEnum::Client->value)
            ->where('user_id_admin_pdv', $adminPdv->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone', 'type', 'user_id_admin_pdv', 'created_at']);

        $latestOrderIds = TagPurchase::query()
            ->selectRaw('MAX(id) as id')
            ->whereHas('user', function ($query) use ($adminPdv) {
                $query->where('user_id_admin_pdv', $adminPdv->id);
            })
            ->whereBetween('created_at', [$from, $to])
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
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'cpf' => $data['cpf'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'phone' => $data['phone'] ?? null,
            'type' => UserTypeEnum::Client->value,
            'pdv_id' => $data['pdv_id'] ?? null,
            'user_id_admin_pdv' => $adminPdv->id,
        ]);

        CreateDefaultUserSettingsJob::dispatch($adminPdv->id);

        return $user;
    }

    public function updateUser(User $adminPdv, int $id, array $data): User
    {
        $user = $this->findUser($adminPdv, $id);

        $fields = array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'cpf' => $data['cpf'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'phone' => $data['phone'] ?? null,
        ], fn($value) => $value !== null);

        if (array_key_exists('pdv_id', $data)) {
            $fields['pdv_id'] = $data['pdv_id'];
        }

        if (!empty($data['password'])) {
            $fields['password'] = Hash::make($data['password']);
        }

        $fields['type'] = UserTypeEnum::Client->value;
        $fields['user_id_admin_pdv'] = $adminPdv->id;

        $user->update($fields);

        return $user->fresh();
    }

    public function deleteUser(User $adminPdv, int $id): void
    {
        $user = $this->findUser($adminPdv, $id);

        $user->delete();
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
