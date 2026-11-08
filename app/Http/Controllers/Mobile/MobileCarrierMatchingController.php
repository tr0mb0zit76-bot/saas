<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LoadBoardPost;
use App\Models\User;
use App\Services\CarrierMatching\CarrierMatchingService;
use App\Support\ExternalParty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileCarrierMatchingController extends Controller
{
    public function __construct(
        private readonly CarrierMatchingService $matching,
    ) {}

    public function storeCargoRequest(Request $request): JsonResponse
    {
        $user = $this->externalUser($request);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'loading_location' => ['required', 'string', 'max:255'],
            'unloading_location' => ['required', 'string', 'max:255'],
            'loading_date' => ['nullable', 'date'],
            'unloading_date' => ['nullable', 'date', 'after_or_equal:loading_date'],
            'transport_type' => ['nullable', 'string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'cargo_name' => ['nullable', 'string', 'max:255'],
            'cargo' => ['nullable', 'array'],
            'cargo.name' => ['nullable', 'string', 'max:255'],
            'cargo.weight_value' => ['nullable', 'numeric', 'min:0'],
            'cargo.weight_kg' => ['nullable', 'numeric', 'min:0'],
            'cargo.weight_unit' => ['nullable', 'string', 'in:kg,t'],
            'cargo.volume_m3' => ['nullable', 'numeric', 'min:0'],
            'cargo.length_m' => ['nullable', 'numeric', 'min:0'],
            'cargo.width_m' => ['nullable', 'numeric', 'min:0'],
            'cargo.height_m' => ['nullable', 'numeric', 'min:0'],
            'cargo.package_count' => ['nullable', 'integer', 'min:0'],
            'cargo.package_type' => ['nullable', 'string', 'max:100'],
            'cargo.description' => ['nullable', 'string', 'max:2000'],
            'cargo.dangerous_goods' => ['nullable', 'boolean'],
            'cargo.dangerous_class' => ['nullable', 'string', 'max:50'],
            'cargo.hs_code' => ['nullable', 'string', 'max:32'],
            'cargo.cargo_type' => ['nullable', 'string', 'max:50'],
        ]);

        if (empty($validated['cargo']['name'] ?? null) && ! empty($validated['cargo_name'] ?? null)) {
            $validated['cargo'] = array_merge($validated['cargo'] ?? [], [
                'name' => $validated['cargo_name'],
            ]);
        }

        if (empty($validated['cargo']['name'] ?? null)) {
            $validated['cargo']['name'] = 'Груз';
        }

        $lead = $this->matching->createCustomerRequest($user, $validated);

        return response()->json([
            'lead' => $this->matching->presentLeadForCustomer($lead),
        ], 201);
    }

    public function cargoRequests(Request $request): JsonResponse
    {
        $user = $this->externalUser($request);

        $leads = $this->matching->listCustomerRequests($user)
            ->map(fn (Lead $lead): array => $this->matching->presentLeadForCustomer($lead))
            ->values();

        return response()->json(['leads' => $leads]);
    }

    public function showCargoRequest(Request $request, Lead $lead): JsonResponse
    {
        $user = $this->externalUser($request);
        abort_unless($this->matching->customerCanViewLead($user, $lead), 404);

        $lead->loadMissing(['cargoItems', 'routePoints']);

        return response()->json([
            'lead' => $this->matching->presentLeadForCustomer($lead),
        ]);
    }

    public function matchingLoads(Request $request): JsonResponse
    {
        $user = $this->externalUser($request, ExternalParty::Carrier);

        return response()->json([
            'posts' => $this->matching->listInvitedPostsForCarrier($user),
        ]);
    }

    public function showMatchingLoad(Request $request, LoadBoardPost $post): JsonResponse
    {
        $user = $this->externalUser($request, ExternalParty::Carrier);
        $carrierId = (int) ($user->contractor_id ?? 0);

        abort_unless($this->matching->isMatchingPost($post), 404);
        abort_unless($this->matching->carrierIsInvited($post, $carrierId), 404);

        $own = $post->offers()
            ->where('carrier_id', $carrierId)
            ->where('created_by', $user->id)
            ->latest('id')
            ->first();

        return response()->json([
            'post' => $this->matching->presentForCarrier($post, $own),
        ]);
    }

    public function storeBid(Request $request, LoadBoardPost $post): JsonResponse
    {
        $user = $this->externalUser($request, ExternalParty::Carrier);

        $validated = $request->validate([
            'carrier_rate' => ['required', 'numeric', 'min:0'],
            'carrier_rate_currency' => ['nullable', 'string', 'size:3'],
            'payment_form' => ['nullable', 'string', 'max:100'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'available_date' => ['nullable', 'date'],
        ]);

        $offer = $this->matching->submitCarrierBid($user, $post, $validated);

        return response()->json([
            'offer' => [
                'id' => $offer->id,
                'status' => $offer->status,
                'carrier_rate' => $offer->carrier_rate !== null ? (float) $offer->carrier_rate : null,
                'carrier_rate_currency' => $offer->carrier_rate_currency,
                'payment_form' => $offer->payment_form,
                'comment' => $offer->comment,
                'available_date' => $offer->available_date?->toDateString(),
            ],
            'post' => $this->matching->presentForCarrier($post->fresh() ?? $post, $offer),
        ], 201);
    }

    private function externalUser(Request $request, ?ExternalParty $party = null): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isExternal(), 403);

        if ($party !== null) {
            abort_unless($user->externalParty() === $party, 403);
        }

        return $user;
    }
}
