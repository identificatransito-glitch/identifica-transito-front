<?php

namespace App\Services;

use App\Enums\UserTypeEnum;
use App\Enums\VehicleUsageProfileEnum;
use App\Jobs\SendTagPurchaseOrderSummaryJob;
use App\Models\DiscountCoupon;
use App\Models\Pdv;
use App\Models\Shipment;
use App\Models\Tag;
use App\Models\TagPurchase;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

class TagPurchaseService
{
    public function countForUser(User $user): int
    {
        $query = TagPurchase::query();

        if ($user->type !== UserTypeEnum::SuperAdmin->value) {
            $query->where('user_id', $user->id);
        }

        return $query->count();
    }

    public function paginate(User $user, ?string $search = null, int|string|null $shipmentStatus = null): LengthAwarePaginator
    {
        $query = TagPurchase::query()->with('shipment')->with('vehicle')->orderByDesc('created_at');

        if ($user->type !== UserTypeEnum::SuperAdmin->value) {
            $query->where('user_id', $user->id);
        }

        $this->applySearchFilters($query, $search, $shipmentStatus);

        $purchases = $query->paginate(5);
        $this->hydratePickupPoints($purchases->getCollection());
        $this->hydrateTagNames($purchases->getCollection());

        return $purchases;
    }

    private function applySearchFilters($query, ?string $search, int|string|null $shipmentStatus = null): void
    {
        if (is_string($shipmentStatus) && $shipmentStatus !== '') {
            $shipmentStatus = intval($shipmentStatus);
        }

        if (is_numeric($shipmentStatus)) {
            $query->whereHas('shipment', function ($shipmentQuery) use ($shipmentStatus) {
                $shipmentQuery->where('status', (int) $shipmentStatus);
            });
        }

        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $normalizedSearch = Str::of($search)
            ->lower()
            ->ascii()
            ->value();

        $statusMap = [
            'approved' => ['approved'],
            'aprovado' => ['approved'],
            'pending' => ['pending', 'in_process'],
            'pendente' => ['pending', 'in_process'],
            'in_process' => ['in_process'],
            'rejected' => ['rejected'],
            'rejeitado' => ['rejected'],
            'recusado' => ['rejected'],
            'cancelled' => ['cancelled'],
            'canceled' => ['cancelled'],
            'cancelado' => ['cancelled'],
        ];

        $statusFilters = $statusMap[$normalizedSearch] ?? [];
        $hasIdFilter = ctype_digit($search);
        $normalizedPlateSearch = Str::of($search)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->value();

        $query->where(function ($query) use ($hasIdFilter, $search, $statusFilters, $normalizedPlateSearch) {
            $query->where('mp_payment_id', 'like', '%' . $search . '%');

            if ($hasIdFilter) {
                $query->orWhere('id', (int) $search);
            }

            if (!empty($statusFilters)) {
                $query->orWhereIn('mp_payment_status', $statusFilters);
            }

            if ($normalizedPlateSearch !== '') {
                $query->orWhereHas('vehicle', function ($vehicleQuery) use ($search, $normalizedPlateSearch) {
                    $vehicleQuery
                        ->where('plate', 'like', '%' . $search . '%')
                        ->orWhereRaw("REPLACE(REPLACE(UPPER(plate), '-', ''), ' ', '') LIKE ?", ['%' . $normalizedPlateSearch . '%']);
                });
            }
        });
    }

    public function findForUser(int $id, User $user): TagPurchase
    {
        $purchase = TagPurchase::with('shipment')->findOrFail($id);

        if ($user->type !== UserTypeEnum::SuperAdmin->value && $purchase->user_id !== $user->id) {
            throw new AuthorizationException('Você não tem permissão para visualizar este pedido.');
        }

        $this->hydratePickupPoints(collect([$purchase]));
        $this->hydrateTagNames(collect([$purchase]));

        return $purchase;
    }

    public function resume(User $user): array
    {
        $query = TagPurchase::query();

        if ($user->type !== UserTypeEnum::SuperAdmin->value) {
            $query->where('user_id', $user->id);
        }

        $counts = $query->selectRaw("
            COUNT(DISTINCT mp_payment_id) as total,
            COUNT(DISTINCT CASE WHEN status = 0 THEN mp_payment_id END) as in_progress,
            COUNT(DISTINCT CASE WHEN status = 1 THEN mp_payment_id END) as completed,
            COUNT(DISTINCT CASE WHEN status IN (2, 3) THEN mp_payment_id END) as failed
        ")->first();

        return [
            'total'       => (int) $counts->total,
            'in_progress' => (int) $counts->in_progress,
            'completed'   => (int) $counts->completed,
            'failed'      => (int) $counts->failed,
        ];
    }

    public function checkout(array $payload, User $user): array
    {
        $vehicleQtyMap = collect($payload['vehicles'])->groupBy('id')->map->count();
        if ($vehicleQtyMap->contains(fn($qty) => $qty > 1)) throw \Illuminate\Validation\ValidationException::withMessages(['vehicles'=>'Selecione apenas um kit por placa.']);
        $vehicleIds    = $vehicleQtyMap->keys()->map(fn($id) => (int) $id)->toArray();
        $requestedCouponQty = (int) $vehicleQtyMap->sum();

        $vehicles = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->get();

        $this->ensureVehiclesBelongToUser($vehicles, $vehicleIds, $user);
        foreach ($vehicles as $vehicle) VehiclePlateGuard::ensureSingleRegistration($vehicle);
        $this->ensureVehiclesNotAlreadyPurchased($vehicles);

        $tagsBySlug = Tag::query()->select('id', 'slug', 'name', 'price')->get()->keyBy('slug');

        $coupon = null;
        $couponCode = null;

        if (!empty($payload['coupon_discount'])) {
            $couponCode = trim((string) $payload['coupon_discount']);
            $coupon = $this->resolveDiscountCoupon($couponCode, $requestedCouponQty);
        }

        $items = $this->buildItems($vehicles, $tagsBySlug, $coupon, $vehicleQtyMap);
        $total = round(collect($items)->sum('price_total'), 2);

        $isDelivery    = (bool) ($payload['is_delivery'] ?? false);
        $shippingPrice = (new ShippingPriceService)->priceFor($payload);
        $paymentTotal = (new ShippingPriceService)->paymentTotal($total,$shippingPrice);
        $delivery      = $isDelivery ? ($payload['delivery_address'] ?? null) : null;
        $pickupPoint   = !$isDelivery ? ($payload['pickup_point'] ?? null) : null;
        $shipmentCarrier = $isDelivery ? 'correios' : 'retirada';
        $paymentMethod = $payload['payment_method'];

        // Desconto 100%: pula o pagamento e aprova automaticamente
        if ($paymentTotal <= 0) {
            $freePaymentId = 'FREE_' . now()->format('YmdHis');

            $purchases = DB::transaction(function () use ($items, $user, $couponCode, $requestedCouponQty, $freePaymentId, $shippingPrice, $delivery, $pickupPoint, $shipmentCarrier, $paymentMethod) {
                $reservedCoupon = null;
                $couponInfo = null;

                if ($couponCode !== null) {
                    $reservedCoupon = $this->reserveDiscountCouponQty($couponCode, $requestedCouponQty);
                    $couponInfo = [
                        'code' => $reservedCoupon->code,
                        'type' => $reservedCoupon->type,
                        'value' => $reservedCoupon->value,
                    ];
                }

                return collect($items)->map(function (array $item) use ($user, $reservedCoupon, $couponInfo, $freePaymentId, $shippingPrice, $delivery, $pickupPoint, $shipmentCarrier, $paymentMethod) {
                    $purchase = TagPurchase::query()->create([
                        'vehicle_id'               => $item['vehicle']->id,
                        'user_id'                  => $user->id,
                        'tag_id'                   => $item['tag']->id,
                        'discount_coupon'          => $reservedCoupon?->id,
                        'coupon_info'              => $couponInfo,
                        'qty'                      => $item['qty'],
                        'price'                    => $item['price'],
                        'price_total'              => 0,
                        'shipping_price'           => $shippingPrice,
                        'delivery'                 => $delivery,
                        'pickup_point'             => $pickupPoint,
                        'mp_preference_id'         => null,
                        'mp_payment_id'            => $freePaymentId,
                        'mp_payment_status'        => 'approved',
                        'mp_payment_status_detail' => 'coupon_full_discount',
                        'boleto_url'               => null,
                        'status'                   => 1,
                        'payment_method'           => $paymentMethod,
                    ]);

                    Shipment::query()->create([
                        'tag_purchase_id' => $purchase->id,
                        'carrier'         => $shipmentCarrier,
                    ]);

                    return $purchase;
                });
            });

            $this->hydratePickupPoints($purchases);
            $this->hydrateTagNames($purchases);

            SendTagPurchaseOrderSummaryJob::dispatch(
                $purchases->pluck('id')->toArray(),
                $user->id,
            );

            return [
                'payment_id'    => $freePaymentId,
                'status'        => 'approved',
                'status_detail' => 'coupon_full_discount',
                'status_label'  => 'aprovado',
                'purchases'     => $purchases,
            ];
        }

        if ($paymentMethod === 'free') throw \Illuminate\Validation\ValidationException::withMessages(['payment_method'=>'Escolha Pix ou boleto para pagar o total, incluindo o frete.']);
        $payment = $this->createPayment($payload, $items, $paymentTotal, $user);

        $mpStatus       = $payment->status ?? 'pending';
        $mpStatusDetail = $payment->status_detail ?? null;
        $boletoUrl      = $payload['payment_method'] === 'boleto'
            ? ($payment->transaction_details->external_resource_url ?? null)
            : null;

        $purchases = DB::transaction(function () use ($items, $user, $couponCode, $requestedCouponQty, $payment, $mpStatus, $mpStatusDetail, $boletoUrl, $shippingPrice, $delivery, $pickupPoint, $shipmentCarrier, $paymentMethod) {
            $reservedCoupon = null;
            $couponInfo = null;

            if ($couponCode !== null) {
                $reservedCoupon = $this->reserveDiscountCouponQty($couponCode, $requestedCouponQty);
                $couponInfo = [
                    'code' => $reservedCoupon->code,
                    'type' => $reservedCoupon->type,
                    'value' => $reservedCoupon->value,
                ];
            }

            return collect($items)->map(function (array $item) use ($user, $reservedCoupon, $couponInfo, $payment, $mpStatus, $mpStatusDetail, $boletoUrl, $shippingPrice, $delivery, $pickupPoint, $shipmentCarrier, $paymentMethod) {
                $purchase = TagPurchase::query()->create([
                    'vehicle_id'               => $item['vehicle']->id,
                    'user_id'                  => $user->id,
                    'tag_id'                   => $item['tag']->id,
                    'discount_coupon'          => $reservedCoupon?->id,
                    'coupon_info'              => $couponInfo,
                    'qty'                      => $item['qty'],
                    'price'                    => $item['price'],
                    'price_total'              => $item['price_total'],
                    'shipping_price'           => $shippingPrice,
                    'delivery'                 => $delivery,
                    'pickup_point'             => $pickupPoint,
                    'mp_preference_id'         => null,
                    'mp_payment_id'            => (string) $payment->id,
                    'mp_payment_status'        => $mpStatus,
                    'mp_payment_status_detail' => $mpStatusDetail,
                    'boleto_url'               => $boletoUrl,
                    'status'                   => $this->resolveNumericStatus($mpStatus),
                    'payment_method'           => $paymentMethod,
                ]);

                Shipment::query()->create([
                    'tag_purchase_id' => $purchase->id,
                    'carrier'         => $shipmentCarrier,
                ]);

                return $purchase;
            });
        });

        $this->hydratePickupPoints($purchases);
        $this->hydrateTagNames($purchases);

        SendTagPurchaseOrderSummaryJob::dispatch(
            $purchases->pluck('id')->toArray(),
            $user->id,
        );

        return $this->buildResponse($payload['payment_method'], $payment, $purchases);
    }

    public function previewCoupon(array $payload, User $user): array
    {
        $vehicleQtyMap = collect($payload['vehicles'])->groupBy('id')->map->count();
        if ($vehicleQtyMap->contains(fn($qty) => $qty > 1)) throw \Illuminate\Validation\ValidationException::withMessages(['vehicles'=>'Selecione apenas um kit por placa.']);
        $vehicleIds    = $vehicleQtyMap->keys()->map(fn($id) => (int) $id)->toArray();
        $requestedCouponQty = (int) $vehicleQtyMap->sum();

        $vehicles = Vehicle::query()->whereIn('id', $vehicleIds)->get();
        $this->ensureVehiclesBelongToUser($vehicles, $vehicleIds, $user);
        foreach ($vehicles as $vehicle) VehiclePlateGuard::ensureSingleRegistration($vehicle);

        $tagsBySlug = Tag::query()->select('id', 'slug', 'name', 'price')->get()->keyBy('slug');

        $coupon = $this->resolveDiscountCoupon($payload['coupon_discount'], $requestedCouponQty);

        $itemsWithCoupon    = $this->buildItems($vehicles, $tagsBySlug, $coupon, $vehicleQtyMap);
        $itemsWithoutCoupon = $this->buildItems($vehicles, $tagsBySlug, null, $vehicleQtyMap);

        $subtotal = round(collect($itemsWithoutCoupon)->sum('price_total'), 2);
        $total    = round(collect($itemsWithCoupon)->sum('price_total'), 2);
        $discount = round($subtotal - $total, 2);

        $items = collect($itemsWithCoupon)->map(fn(array $item) => [
            'vehicle_id'  => $item['vehicle']->id,
            'plate'       => $item['vehicle']->plate,
            'tag_name'    => $item['tag']->name,
            'qty'         => $item['qty'],
            'unit_price'  => $item['price'],
            'unit_total'  => $item['qty'] > 0 ? round($item['price_total'] / $item['qty'], 2) : $item['price'],
            'price_total' => $item['price_total'],
        ])->values()->all();

        return [
            'coupon' => [
                'code'  => $coupon->code,
                'type'  => $coupon->type,
                'value' => $coupon->value,
            ],
            'items'    => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total'    => $total,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function ensureVehiclesBelongToUser(Collection $vehicles, array $vehicleIds, User $user): void
    {
        if ($vehicles->count() !== count($vehicleIds)) {
            throw new \InvalidArgumentException('Um ou mais veículos informados não foram encontrados.');
        }

        $foreign = $vehicles->filter(fn(Vehicle $v) => $v->user_id !== $user->id);

        if ($foreign->isNotEmpty()) {
            throw new AuthorizationException('Você não tem permissão para comprar tags de veículos de outro usuário.');
        }
    }

    private function ensureVehiclesNotAlreadyPurchased(Collection $vehicles): void
    {
        $alreadyPurchasedVehicleIds = TagPurchase::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->where('mp_payment_status', 'approved')
            ->pluck('vehicle_id')
            ->unique();

        if ($alreadyPurchasedVehicleIds->isEmpty()) {
            return;
        }

        $plates = $vehicles
            ->whereIn('id', $alreadyPurchasedVehicleIds->all())
            ->pluck('plate')
            ->implode(', ');

        throw new \InvalidArgumentException(
            "Já existe uma compra aprovada para a(s) placa(s): {$plates}. Não é possível comprar novamente para o mesmo veículo."
        );
    }

    private function resolveDiscountCoupon(string $code, ?int $requiredQty = null): DiscountCoupon
    {
        $normalizedCode = mb_strtoupper(trim($code));

        $coupon = DiscountCoupon::query()
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->where(function ($query) {
                $query->whereNull('expiration_date')
                    ->orWhere('expiration_date', '>=', now());
            })
            ->first();

        if (!$coupon) {
            throw new \InvalidArgumentException('Cupom de desconto inválido ou expirado.');
        }

        if ($requiredQty !== null && $coupon->qty !== null && $coupon->qty < $requiredQty) {
            throw new \InvalidArgumentException('Cupom de desconto sem quantidade disponível.');
        }

        return $coupon;
    }

    private function reserveDiscountCouponQty(string $code, int $requiredQty): DiscountCoupon
    {
        $normalizedCode = mb_strtoupper(trim($code));

        $coupon = DiscountCoupon::query()
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->where(function ($query) {
                $query->whereNull('expiration_date')
                    ->orWhere('expiration_date', '>=', now());
            })
            ->lockForUpdate()
            ->first();

        if (!$coupon) {
            throw new \InvalidArgumentException('Cupom de desconto inválido ou expirado.');
        }

        if ($coupon->qty !== null && $coupon->qty < $requiredQty) {
            throw new \InvalidArgumentException('Cupom de desconto sem quantidade disponível.');
        }

        if ($coupon->qty !== null) {
            $coupon->decrement('qty', $requiredQty);
        }

        return $coupon->fresh();
    }

    private function buildItems(Collection $vehicles, Collection $tagsBySlug, ?DiscountCoupon $coupon, \Illuminate\Support\Collection $vehicleQtyMap): array
    {
        return $vehicles->map(function (Vehicle $vehicle) use ($tagsBySlug, $coupon, $vehicleQtyMap) {
            $slug = VehicleUsageProfileEnum::tryFrom((int) $vehicle->usage_profile)?->tagColor();
            $tag  = $tagsBySlug->get($slug);

            if (!$tag) {
                throw new \RuntimeException("Tag não encontrada para o perfil de uso do veículo {$vehicle->plate}.");
            }

            $qty        = (int) $vehicleQtyMap->get($vehicle->id, 1);
            $price      = (float) $tag->price;
            $unitTotal  = $price;

            if ($coupon) {
                $unitTotal = $coupon->type === 1
                    ? $price - ($price * (float) $coupon->value / 100)
                    : $price - (float) $coupon->value;

                $unitTotal = round(max(0, $unitTotal), 2);
            }

            return [
                'vehicle'     => $vehicle,
                'tag'         => $tag,
                'qty'         => $qty,
                'price'       => $price,
                'price_total' => round($unitTotal * $qty, 2),
            ];
        })->toArray();
    }

    private function createPayment(array $payload, array $items, float $total, User $user): object
    {
        MercadoPagoConfig::setAccessToken(config('mercadopago.access_token'));

        $client      = new PaymentClient();
        $payer       = $this->buildPayer($user);
        $description = collect($items)
            ->map(fn($i) => "Tag {$i['tag']->name} - {$i['vehicle']->plate}")
            ->implode(', ');
        $date_of_expiration = gmdate('Y-m-d\TH:i:s.vP', strtotime('+10 minutes'));
        $notificationUrl    = config('mercadopago.webhook_url') ?: null;

        $base = array_filter([
            'notification_url' => $notificationUrl,
        ]);

        return match ($payload['payment_method']) {
            'pix' => $client->create(array_merge($base, [
                'transaction_amount' => $total,
                'description'        => $description,
                'payment_method_id'  => 'pix',
                'payer'              => $payer,
                'date_of_expiration' => $date_of_expiration,
            ])),

            'boleto' => $client->create(array_merge($base, [
                'transaction_amount' => $total,
                'description'        => $description,
                'payment_method_id'  => 'bolbradesco',
                'payer'              => array_merge($payer, [
                    'address' => [
                        'zip_code'      => $payload['payer_zip_code'],
                        'street_name'   => $payload['payer_street'],
                        'street_number' => $payload['payer_number'],
                        'neighborhood'  => $payload['payer_neighborhood'],
                        'city'          => $payload['payer_city'],
                        'federal_unit'  => strtoupper($payload['payer_state']),
                    ],
                ]),
            ])),


            'credit_card' => $client->create(array_merge($base, [
                'transaction_amount' => $total,
                'description'        => $description,
                'token'              => $payload['card_token'],
                'installments'       => (int) $payload['installments'],
                'payment_method_id'  => $payload['payment_method_id'],
                'issuer_id'          => $payload['issuer_id'],
                'payer'              => $payer,
            ])),
        };
    }

    private function buildPayer(User $user): array
    {
        $nameParts  = explode(' ', $user->name, 2);
        $testEmail  = config('mercadopago.test_payer_email');
        // $payerEmail = ($testEmail && !app()->isProduction()) ? $testEmail : $user->email;
        $payerEmail = $user->email;
        return [
            'email'          => $payerEmail,
            'first_name'     => $nameParts[0],
            'last_name'      => $nameParts[1] ?? '',
            'identification' => [
                'type'   => 'CPF',
                'number' => preg_replace('/\D/', '', $user->cpf ?? ''),
            ],
        ];
    }

    private function buildResponse(string $method, object $payment, Collection $purchases): array
    {
        $base = [
            'payment_id'            => $payment->id,
            'status'                => $payment->status,
            'status_detail'         => $payment->status_detail ?? null,
            'status_label'          => $this->resolveStatusLabel($payment->status ?? ''),
            'purchases'             => $purchases,
        ];

        return match ($method) {
            'pix' => array_merge($base, [
                'qr_code'        => $payment->point_of_interaction->transaction_data->qr_code ?? null,
                'qr_code_base64' => $payment->point_of_interaction->transaction_data->qr_code_base64 ?? null,
                'ticket_url'     => $payment->point_of_interaction->transaction_data->ticket_url ?? null,
            ]),

            'boleto' => array_merge($base, [
                'barcode'    => $payment->barcode->content ?? null,
                'boleto_url' => $payment->transaction_details->external_resource_url ?? null,
            ]),

            'credit_card' => $base,
        };
    }

    private function resolveNumericStatus(string $mpStatus): int
    {
        return match ($mpStatus) {
            'approved'                  => 1,
            'cancelled'                 => 2,
            'rejected'                  => 3,
            default                     => 0, // pending, in_process
        };
    }

    private function resolveStatusLabel(string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved'                  => 'aprovado',
            'pending', 'in_process'     => 'pendente',
            'rejected'                  => 'rejeitado',
            'cancelled'                 => 'cancelado',
            default                     => 'pendente',
        };
    }

    private function hydratePickupPoints(Collection $purchases): void
    {
        $pickupPointIds = $purchases
            ->map(fn(TagPurchase $purchase) => data_get($purchase->pickup_point, 'id'))
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($pickupPointIds->isEmpty()) {
            return;
        }

        $pickupPointsById = Pdv::query()
            ->withTrashed()
            ->whereIn('id', $pickupPointIds->all())
            ->get()
            ->keyBy('id');

        $purchases->each(function (TagPurchase $purchase) use ($pickupPointsById): void {
            $pickupPoint = $purchase->pickup_point;

            if (!is_array($pickupPoint)) {
                return;
            }

            $pickupPointId = data_get($pickupPoint, 'id');

            if (!is_numeric($pickupPointId)) {
                return;
            }

            $fullPickupPoint = $pickupPointsById->get((int) $pickupPointId);

            if ($fullPickupPoint) {
                $purchase->setAttribute('pickup_point', $fullPickupPoint->toArray());
            }
        });
    }

    private function hydrateTagNames(Collection $purchases): void
    {
        $tagIds = $purchases
            ->map(fn(TagPurchase $purchase) => $purchase->tag_id)
            ->filter(fn($id) => is_numeric($id))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($tagIds->isEmpty()) {
            return;
        }

        $tagsById = Tag::query()
            ->select('id', 'name', 'slug')
            ->whereIn('id', $tagIds->all())
            ->get()
            ->keyBy('id');

        $purchases->each(function (TagPurchase $purchase) use ($tagsById): void {
            $tag = $tagsById->get((int) $purchase->tag_id);

            if ($tag) {
                $purchase->setAttribute('tag_name', $tag->name);
                $purchase->setAttribute('tag_slug', $tag->slug);
            }
        });
    }

    public function syncAllPaymentStatuses(): array
    {
        MercadoPagoConfig::setAccessToken(config('mercadopago.access_token'));

        $paymentIds = TagPurchase::query()
            ->whereNotNull('mp_payment_id')
            ->whereIn('mp_payment_status', ['pending', 'in_process'])
            ->distinct()
            ->pluck('mp_payment_id');

        $client  = new PaymentClient();
        $synced  = 0;
        $errors  = [];

        foreach ($paymentIds as $paymentId) {
            try {
                $requestOptions = new RequestOptions();
                $requestOptions->setCustomHeaders(['X-Idempotency-Key: ' . uniqid()]);

                $payment        = $client->get($paymentId, $requestOptions);
                $mpStatus       = $payment->status ?? 'pending';
                $mpStatusDetail = $payment->status_detail ?? null;

                TagPurchase::query()
                    ->where('mp_payment_id', $paymentId)
                    ->update([
                        'mp_payment_status'        => $mpStatus,
                        'mp_payment_status_detail' => $mpStatusDetail,
                        'status'                   => $this->resolveNumericStatus($mpStatus),
                    ]);

                $synced++;
            } catch (\Throwable $e) {
                $errors[] = ['payment_id' => $paymentId, 'error' => $e->getMessage()];
            }
        }

        return [
            'total_checked' => $paymentIds->count(),
            'synced'        => $synced,
            'errors'        => $errors,
        ];
    }

    public function handlePaymentWebhook(string $paymentId): void
    {
        MercadoPagoConfig::setAccessToken(config('mercadopago.access_token'));

        $client         = new PaymentClient();
        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders(['X-Idempotency-Key: ' . uniqid()]);

        $payment        = $client->get($paymentId, $requestOptions);
        $mpStatus       = $payment->status ?? 'pending';
        $mpStatusDetail = $payment->status_detail ?? null;

        TagPurchase::query()
            ->where('mp_payment_id', $paymentId)
            ->update([
                'mp_payment_status'        => $mpStatus,
                'mp_payment_status_detail' => $mpStatusDetail,
                'status'                   => $this->resolveNumericStatus($mpStatus),
            ]);
    }

    public function checkPaymentStatus(string $paymentId): array
    {
        MercadoPagoConfig::setAccessToken(config('mercadopago.access_token'));

        $client          = new PaymentClient();
        $requestOptions  = new RequestOptions();
        $requestOptions->setCustomHeaders(['X-Idempotency-Key: ' . uniqid()]);

        try {
            $payment = $client->get($paymentId, $requestOptions);

            return [
                'payment_id'   => $payment->id,
                'status'       => $payment->status,
                'status_detail' => $payment->status_detail ?? null,
                'status_label' => $this->resolveStatusLabel($payment->status ?? ''),
            ];
        } catch (MPApiException $e) {
            return [
                'error'      => $e->getMessage(),
                'error_code' => $e->getCode(),
            ];
        }
    }
}
