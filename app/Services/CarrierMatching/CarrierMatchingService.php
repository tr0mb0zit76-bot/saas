<?php

namespace App\Services\CarrierMatching;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LeadCargoItem;
use App\Models\LeadRoutePoint;
use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Models\User;
use App\Support\ExternalParty;
use App\Support\LeadCargoItemPayloadNormalizer;
use App\Support\LeadPerformerPayloadNormalizer;
use App\Support\LeadStatus;
use App\Support\LoadBoardOfferSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CarrierMatchingService
{
    public const SOURCE_CUSTOMER_REQUEST = 'traklo_customer_request';

    public const META_KEY = 'carrier_matching';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCustomerRequest(User $customer, array $payload): Lead
    {
        $this->assertExternalCustomer($customer);

        $contractorId = (int) ($customer->contractor_id ?? 0);
        abort_unless($contractorId > 0, 403);

        return DB::transaction(function () use ($customer, $payload, $contractorId): Lead {
            $loading = trim((string) ($payload['loading_location'] ?? ''));
            $unloading = trim((string) ($payload['unloading_location'] ?? ''));

            $contactName = trim((string) ($customer->name ?? ''));
            $source = $contactName !== ''
                ? self::SOURCE_CUSTOMER_REQUEST.' · '.$contactName
                : self::SOURCE_CUSTOMER_REQUEST;

            $lead = Lead::query()->create([
                'number' => $this->nextLeadNumber(),
                'status' => LeadStatus::values()[0],
                'source' => $source,
                'counterparty_id' => $contractorId,
                'title' => (string) ($payload['title'] ?? sprintf('Просчёт: %s → %s', $loading, $unloading)),
                'description' => $payload['comment'] ?? null,
                'loading_location' => $loading,
                'unloading_location' => $unloading,
                'planned_shipping_date' => $payload['loading_date'] ?? null,
                'transport_type' => $payload['transport_type'] ?? null,
                'created_by' => $customer->id,
                'updated_by' => $customer->id,
                'metadata' => [
                    'privacy_notice_ack' => true,
                    'created_via' => 'customer_cabinet',
                    'contractor_contact_id' => $customer->contractor_contact_id,
                    'contact_name' => $contactName !== '' ? $contactName : null,
                ],
            ]);

            $this->syncRoutePoints($lead, $payload);
            $this->syncCargoItem($lead, $payload);

            return $lead->fresh(['cargoItems', 'routePoints']) ?? $lead;
        });
    }

    /**
     * @return Collection<int, Lead>
     */
    public function listCustomerRequests(User $customer): Collection
    {
        $this->assertExternalCustomer($customer);
        $contractorId = (int) ($customer->contractor_id ?? 0);
        abort_unless($contractorId > 0, 403);

        return Lead::query()
            ->with(['cargoItems', 'routePoints'])
            ->where('counterparty_id', $contractorId)
            ->where(function ($query): void {
                $query->where('source', self::SOURCE_CUSTOMER_REQUEST)
                    ->orWhere('source', 'like', self::SOURCE_CUSTOMER_REQUEST.' · %');
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    public function customerCanViewLead(User $customer, Lead $lead): bool
    {
        if (! $customer->isExternal() || $customer->externalParty() !== ExternalParty::Customer) {
            return false;
        }

        return (int) $lead->counterparty_id === (int) $customer->contractor_id
            && self::isCustomerRequestSource($lead->source);
    }

    public static function isCustomerRequestSource(mixed $source): bool
    {
        $value = trim((string) $source);

        return $value === self::SOURCE_CUSTOMER_REQUEST
            || str_starts_with($value, self::SOURCE_CUSTOMER_REQUEST.' · ');
    }

    /**
     * @param  list<int>  $carrierIds
     */
    public function shareLeadWithCarriers(Lead $lead, User $staff, array $carrierIds): LoadBoardPost
    {
        abort_if($staff->isExternal(), 403);

        $carrierIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $carrierIds,
        ), static fn (int $id): bool => $id > 0)));

        if ($carrierIds === []) {
            throw ValidationException::withMessages([
                'carrier_ids' => 'Выберите хотя бы одного перевозчика.',
            ]);
        }

        $validIds = Contractor::query()
            ->whereIn('id', $carrierIds)
            ->whereIn('type', ['carrier', 'both', 'contractor'])
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $missing = array_values(array_diff($carrierIds, $validIds));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'carrier_ids' => 'Некоторые перевозчики недоступны в этом tenant.',
            ]);
        }

        $lead->loadMissing(['cargoItems', 'routePoints', 'counterparty:id,name']);

        return DB::transaction(function () use ($lead, $staff, $validIds): LoadBoardPost {
            $existing = LoadBoardPost::query()
                ->where('lead_id', $lead->id)
                ->where('metadata->'.self::META_KEY.'->enabled', true)
                ->whereNull('closed_at')
                ->latest('id')
                ->first();

            $cargo = $lead->cargoItems->first();
            $loadingPoint = $lead->routePoints->firstWhere('type', 'loading') ?? $lead->routePoints->first();
            $unloadingPoint = $lead->routePoints->where('type', 'unloading')->last()
                ?? $lead->routePoints->last();
            $metadataCargo = is_array($cargo?->metadata) ? $cargo->metadata : [];

            $attrs = [
                'lead_id' => $lead->id,
                'order_id' => null,
                'customer_id' => $lead->counterparty_id,
                'seller_id' => $staff->id,
                'status' => 'new',
                'priority' => 'normal',
                'title' => $lead->title ?: ('Груз по лиду #'.($lead->number ?? $lead->id)),
                'loading_location' => $loadingPoint?->address ?? $lead->loading_location,
                'unloading_location' => $unloadingPoint?->address ?? $lead->unloading_location,
                'loading_date' => $loadingPoint?->planned_date?->toDateString()
                    ?? $lead->planned_shipping_date?->toDateString(),
                'unloading_date' => $unloadingPoint?->planned_date?->toDateString(),
                'cargo_name' => $cargo?->name,
                'cargo_weight' => $cargo?->weight_kg !== null
                    ? round(((float) $cargo->weight_kg) / 1000, 2)
                    : null,
                'cargo_volume' => $cargo?->volume_m3,
                'length' => $metadataCargo['length_m'] ?? null,
                'width' => $metadataCargo['width_m'] ?? null,
                'height' => $metadataCargo['height_m'] ?? null,
                'transport_type' => $lead->transport_type ?? $cargo?->cargo_type,
                'requirements' => $cargo?->description,
                // Intentionally omit customer_rate / seller_comment for carrier-facing shares.
                'customer_rate' => null,
                'seller_comment' => null,
                'published_at' => now(),
                'metadata' => [
                    self::META_KEY => [
                        'enabled' => true,
                        'invited_carrier_ids' => $validIds,
                        'shared_by' => $staff->id,
                        'shared_at' => now()->toIso8601String(),
                    ],
                ],
            ];

            if ($existing instanceof LoadBoardPost) {
                $meta = is_array($existing->metadata) ? $existing->metadata : [];
                $matching = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
                $mergedIds = array_values(array_unique(array_merge(
                    array_map('intval', $matching['invited_carrier_ids'] ?? []),
                    $validIds,
                )));
                $attrs['metadata'][self::META_KEY]['invited_carrier_ids'] = $mergedIds;
                $attrs['metadata'][self::META_KEY]['shared_by'] = $staff->id;
                $attrs['metadata'][self::META_KEY]['shared_at'] = now()->toIso8601String();
                $existing->fill($attrs)->save();

                return $existing->fresh() ?? $existing;
            }

            return LoadBoardPost::query()->create($attrs);
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listInvitedPostsForCarrier(User $carrier): Collection
    {
        $this->assertExternalCarrier($carrier);
        $carrierId = (int) ($carrier->contractor_id ?? 0);
        abort_unless($carrierId > 0, 403);

        return LoadBoardPost::query()
            ->where('metadata->'.self::META_KEY.'->enabled', true)
            ->whereNull('closed_at')
            ->whereNotIn('status', ['closed', 'cancelled', 'no_options'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(fn (LoadBoardPost $post): bool => $this->carrierIsInvited($post, $carrierId))
            ->map(function (LoadBoardPost $post) use ($carrier, $carrierId): array {
                $own = $post->offers()
                    ->where('carrier_id', $carrierId)
                    ->where('created_by', $carrier->id)
                    ->latest('id')
                    ->first();

                return $this->presentForCarrier($post, $own);
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitCarrierBid(User $carrier, LoadBoardPost $post, array $payload): LoadBoardOffer
    {
        $this->assertExternalCarrier($carrier);
        $carrierId = (int) ($carrier->contractor_id ?? 0);
        abort_unless($carrierId > 0, 403);
        abort_unless($this->isMatchingPost($post), 404);
        abort_unless($this->carrierIsInvited($post, $carrierId), 403);
        abort_if($post->closed_at !== null || in_array($post->status, ['closed', 'cancelled', 'no_options'], true), 422);

        $rate = round((float) $payload['carrier_rate'], 2);
        $currency = strtoupper((string) ($payload['carrier_rate_currency'] ?? 'RUB'));

        return DB::transaction(function () use ($carrier, $post, $carrierId, $rate, $currency, $payload): LoadBoardOffer {
            $offer = LoadBoardOffer::query()
                ->where('load_board_post_id', $post->id)
                ->where('carrier_id', $carrierId)
                ->where('created_by', $carrier->id)
                ->whereIn('status', ['proposed', 'selected'])
                ->latest('id')
                ->first();

            $attrs = [
                'load_board_post_id' => $post->id,
                'carrier_id' => $carrierId,
                'created_by' => $carrier->id,
                'status' => 'proposed',
                'source' => LoadBoardOfferSource::TRAKLO_CARRIER,
                'carrier_rate' => $rate,
                'carrier_rate_currency' => $currency,
                'payment_form' => $payload['payment_form'] ?? null,
                'comment' => $payload['comment'] ?? null,
                'available_date' => $payload['available_date'] ?? null,
            ];

            if ($offer instanceof LoadBoardOffer) {
                $offer->fill($attrs)->save();
            } else {
                $offer = LoadBoardOffer::query()->create($attrs);
            }

            if (! in_array($post->status, ['closed', 'cancelled', 'no_options', 'seller_review'], true)) {
                $post->update(['status' => 'has_offers']);
            }

            return $offer->fresh() ?? $offer;
        });
    }

    /**
     * Accept bid onto Lead performers only — never creates/updates Order.
     */
    public function acceptBidOntoLead(User $staff, LoadBoardPost $post, LoadBoardOffer $offer): Lead
    {
        abort_if($staff->isExternal(), 403);
        abort_unless($this->isMatchingPost($post), 404);
        abort_unless((int) $offer->load_board_post_id === (int) $post->id, 404);
        abort_unless($post->lead_id !== null, 422);

        $lead = Lead::query()->findOrFail($post->lead_id);
        $offer->loadMissing('carrier:id,name');

        return DB::transaction(function () use ($staff, $post, $offer, $lead): Lead {
            $post->offers()->where('id', '!=', $offer->id)->update(['status' => 'rejected']);
            $offer->update([
                'status' => 'approved',
                'selected_at' => $offer->selected_at ?? now(),
            ]);

            $meta = is_array($post->metadata) ? $post->metadata : [];
            $matching = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];
            $matching['accepted_offer_id'] = $offer->id;
            $matching['accepted_by'] = $staff->id;
            $matching['accepted_at'] = now()->toIso8601String();
            $meta[self::META_KEY] = $matching;

            $post->update([
                'status' => 'closed',
                'accepted_offer_id' => $offer->id,
                'accepted_by' => $staff->id,
                'accepted_at' => now(),
                'closed_at' => now(),
                'metadata' => $meta,
            ]);

            $performers = LeadPerformerPayloadNormalizer::normalizeList([
                [
                    'stage' => 'leg_1',
                    'contractor_id' => $offer->carrier_id,
                    'contractor_name' => $offer->carrier?->name,
                    'estimated_cost' => $offer->carrier_rate !== null ? (float) $offer->carrier_rate : null,
                ],
            ]);

            $leadMeta = is_array($lead->metadata) ? $lead->metadata : [];
            $leadMeta[self::META_KEY] = [
                'accepted_offer_id' => $offer->id,
                'load_board_post_id' => $post->id,
                'carrier_id' => $offer->carrier_id,
                'carrier_rate' => $offer->carrier_rate,
                'carrier_rate_currency' => $offer->carrier_rate_currency,
                'accepted_at' => now()->toIso8601String(),
            ];

            $lead->forceFill([
                'performers' => $performers,
                'calculated_cost' => $offer->carrier_rate,
                'metadata' => $leadMeta,
                'updated_by' => $staff->id,
            ])->save();

            return $lead->fresh(['cargoItems', 'routePoints']) ?? $lead;
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listOffersForStaff(LoadBoardPost $post): Collection
    {
        abort_unless($this->isMatchingPost($post), 404);

        return $post->offers()
            ->with('carrier:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (LoadBoardOffer $offer): array => [
                'id' => $offer->id,
                'carrier_id' => $offer->carrier_id,
                'carrier_name' => $offer->carrier?->name,
                'status' => $offer->status,
                'source' => $offer->source,
                'carrier_rate' => $offer->carrier_rate !== null ? (float) $offer->carrier_rate : null,
                'carrier_rate_currency' => $offer->carrier_rate_currency,
                'payment_form' => $offer->payment_form,
                'comment' => $offer->comment,
                'available_date' => $offer->available_date?->toDateString(),
                'created_at' => $offer->created_at?->toIso8601String(),
            ]);
    }

    public function matchingPostForLead(Lead $lead): ?LoadBoardPost
    {
        return LoadBoardPost::query()
            ->where('lead_id', $lead->id)
            ->where('metadata->'.self::META_KEY.'->enabled', true)
            ->latest('id')
            ->first();
    }

    public function isMatchingPost(LoadBoardPost $post): bool
    {
        return (bool) data_get($post->metadata, self::META_KEY.'.enabled', false);
    }

    public function carrierIsInvited(LoadBoardPost $post, int $carrierId): bool
    {
        $ids = data_get($post->metadata, self::META_KEY.'.invited_carrier_ids', []);
        if (! is_array($ids)) {
            return false;
        }

        return in_array($carrierId, array_map('intval', $ids), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForCarrier(LoadBoardPost $post, ?LoadBoardOffer $ownOffer = null): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'loading_location' => $post->loading_location,
            'unloading_location' => $post->unloading_location,
            'loading_date' => $post->loading_date?->toDateString(),
            'unloading_date' => $post->unloading_date?->toDateString(),
            'cargo_name' => $post->cargo_name,
            'cargo_weight' => $post->cargo_weight !== null ? (float) $post->cargo_weight : null,
            'cargo_volume' => $post->cargo_volume !== null ? (float) $post->cargo_volume : null,
            'length' => $post->length !== null ? (float) $post->length : null,
            'width' => $post->width !== null ? (float) $post->width : null,
            'height' => $post->height !== null ? (float) $post->height : null,
            'transport_type' => $post->transport_type,
            'requirements' => $post->requirements,
            'status' => $post->status,
            'own_offer' => $ownOffer ? [
                'id' => $ownOffer->id,
                'status' => $ownOffer->status,
                'carrier_rate' => $ownOffer->carrier_rate !== null ? (float) $ownOffer->carrier_rate : null,
                'carrier_rate_currency' => $ownOffer->carrier_rate_currency,
                'payment_form' => $ownOffer->payment_form,
                'comment' => $ownOffer->comment,
                'available_date' => $ownOffer->available_date?->toDateString(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentLeadForCustomer(Lead $lead): array
    {
        $cargo = $lead->cargoItems->first();

        return [
            'id' => $lead->id,
            'number' => $lead->number,
            'status' => $lead->status,
            'status_label' => LeadStatus::label((string) $lead->status),
            'title' => $lead->title,
            'loading_location' => $lead->loading_location,
            'unloading_location' => $lead->unloading_location,
            'planned_shipping_date' => $lead->planned_shipping_date?->toDateString(),
            'transport_type' => $lead->transport_type,
            'cargo' => $cargo ? LeadCargoItemPayloadNormalizer::toFrontend($cargo) : null,
            'notice' => 'Просчёт обрабатывается экспедитором. Данные маршрута и груза могут быть переданы выбранным перевозчикам для подбора ставки.',
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncRoutePoints(Lead $lead, array $payload): void
    {
        LeadRoutePoint::query()->where('lead_id', $lead->id)->delete();

        $loading = trim((string) ($payload['loading_location'] ?? ''));
        $unloading = trim((string) ($payload['unloading_location'] ?? ''));

        if ($loading !== '') {
            LeadRoutePoint::query()->create([
                'lead_id' => $lead->id,
                'type' => 'loading',
                'stage' => 'loading',
                'sequence' => 1,
                'address' => $loading,
                'planned_date' => $payload['loading_date'] ?? null,
            ]);
        }

        if ($unloading !== '') {
            LeadRoutePoint::query()->create([
                'lead_id' => $lead->id,
                'type' => 'unloading',
                'stage' => 'unloading',
                'sequence' => 2,
                'address' => $unloading,
                'planned_date' => $payload['unloading_date'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncCargoItem(Lead $lead, array $payload): void
    {
        LeadCargoItem::query()->where('lead_id', $lead->id)->delete();

        $cargoPayload = is_array($payload['cargo'] ?? null) ? $payload['cargo'] : $payload;
        if (! LeadCargoItemPayloadNormalizer::isMeaningful($cargoPayload)
            && trim((string) ($cargoPayload['name'] ?? '')) === ''
            && ($cargoPayload['weight_kg'] ?? $cargoPayload['weight_value'] ?? null) === null
        ) {
            $name = trim((string) ($payload['cargo_name'] ?? ''));
            if ($name === '') {
                return;
            }
            $cargoPayload['name'] = $name;
        }

        if (! isset($cargoPayload['name']) || trim((string) $cargoPayload['name']) === '') {
            $cargoPayload['name'] = (string) ($payload['cargo_name'] ?? 'Груз');
        }

        $normalized = LeadCargoItemPayloadNormalizer::toDatabase($cargoPayload);
        LeadCargoItem::query()->create([
            'lead_id' => $lead->id,
            ...$normalized,
        ]);
    }

    private function nextLeadNumber(): string
    {
        $prefix = 'LD-'.now()->format('ymd');
        $sequence = Lead::query()
            ->where('number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%03d', $prefix, $sequence);
    }

    private function assertExternalCustomer(User $user): void
    {
        abort_unless($user->isExternal() && $user->externalParty() === ExternalParty::Customer, 403);
    }

    private function assertExternalCarrier(User $user): void
    {
        abort_unless($user->isExternal() && $user->externalParty() === ExternalParty::Carrier, 403);
    }
}
