<?php

namespace App\Http\Controllers\CarrierMatching;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LoadBoardOffer;
use App\Models\LoadBoardPost;
use App\Models\User;
use App\Services\CarrierMatching\CarrierMatchingService;
use App\Support\RoleAccess;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadCarrierMatchingController extends Controller
{
    public function __construct(
        private readonly CarrierMatchingService $matching,
    ) {}

    public function share(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->isExternal(), 403);
        abort_unless(RoleAccess::canAccessVisibilityArea($user, 'leads'), 403);

        $tenantId = TenantContext::id();
        abort_if($tenantId === null, 403, 'Tenant context required.');

        $validated = $request->validate([
            'carrier_ids' => ['required', 'array', 'min:1'],
            'carrier_ids.*' => [
                'integer',
                'min:1',
                Rule::exists('contractors', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        $post = $this->matching->shareLeadWithCarriers(
            $lead,
            $user,
            array_map('intval', $validated['carrier_ids']),
        );

        return response()->json([
            'post' => [
                'id' => $post->id,
                'lead_id' => $post->lead_id,
                'status' => $post->status,
                'invited_carrier_ids' => data_get($post->metadata, CarrierMatchingService::META_KEY.'.invited_carrier_ids', []),
            ],
        ], 201);
    }

    public function offers(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->isExternal(), 403);
        abort_unless(RoleAccess::canAccessVisibilityArea($user, 'leads'), 403);

        $post = $this->matching->matchingPostForLead($lead);
        if ($post === null) {
            return response()->json(['post' => null, 'offers' => []]);
        }

        return response()->json([
            'post' => [
                'id' => $post->id,
                'status' => $post->status,
                'closed_at' => $post->closed_at?->toIso8601String(),
            ],
            'offers' => $this->matching->listOffersForStaff($post),
        ]);
    }

    public function acceptOffer(Request $request, Lead $lead, LoadBoardOffer $offer): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->isExternal(), 403);
        abort_unless(RoleAccess::canAccessVisibilityArea($user, 'leads'), 403);

        $post = $this->matching->matchingPostForLead($lead);
        abort_unless($post instanceof LoadBoardPost, 404);
        abort_unless((int) $offer->load_board_post_id === (int) $post->id, 404);

        $updatedLead = $this->matching->acceptBidOntoLead($user, $post, $offer);

        return response()->json([
            'lead' => [
                'id' => $updatedLead->id,
                'number' => $updatedLead->number,
                'status' => $updatedLead->status,
                'performers' => $updatedLead->performers,
                'calculated_cost' => $updatedLead->calculated_cost !== null
                    ? (float) $updatedLead->calculated_cost
                    : null,
                'order_id' => null,
            ],
            'post_id' => $post->id,
            'offer_id' => $offer->id,
        ]);
    }

    public function saveQuote(Request $request, Lead $lead): JsonResponse
    {
        $user = $this->assertStaffLeadAccess($request);

        $validated = $request->validate([
            'quoted_price' => ['required', 'numeric', 'min:0.01'],
            'quoted_currency' => ['nullable', 'string', 'size:3'],
        ]);

        $updated = $this->matching->saveQuoteDraft(
            $lead,
            $user,
            $validated['quoted_price'],
            $validated['quoted_currency'] ?? null,
        );

        return response()->json(['lead' => $this->matching->presentQuoteForStaff($updated)]);
    }

    public function sendQuote(Request $request, Lead $lead): JsonResponse
    {
        $user = $this->assertStaffLeadAccess($request);
        $updated = $this->matching->sendQuoteToClient($lead, $user);

        return response()->json(['lead' => $this->matching->presentQuoteForStaff($updated)]);
    }

    public function agreeQuote(Request $request, Lead $lead): JsonResponse
    {
        $user = $this->assertStaffLeadAccess($request);
        $updated = $this->matching->agreeQuote($lead, $user);

        return response()->json(['lead' => $this->matching->presentQuoteForStaff($updated)]);
    }

    private function assertStaffLeadAccess(Request $request): User
    {
        $user = $request->user();
        abort_if($user === null || $user->isExternal(), 403);
        abort_unless(RoleAccess::canAccessVisibilityArea($user, 'leads'), 403);

        return $user;
    }
}
