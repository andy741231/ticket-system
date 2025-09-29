<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Newsletter\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterCampaignController extends Controller
{
    public function drafts(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 6);
        if ($perPage < 1) {
            $perPage = 6;
        }
        if ($perPage > 24) {
            $perPage = 24;
        }

        $query = Campaign::query()
            ->select(['id', 'name', 'subject', 'updated_at', 'created_at'])
            ->where('status', 'draft')
            ->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $drafts = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $drafts->items(),
            'meta' => [
                'current_page' => $drafts->currentPage(),
                'last_page' => $drafts->lastPage(),
                'per_page' => $drafts->perPage(),
                'total' => $drafts->total(),
                'from' => $drafts->firstItem(),
                'to' => $drafts->lastItem(),
            ],
            'links' => [
                'prev' => $drafts->previousPageUrl(),
                'next' => $drafts->nextPageUrl(),
            ],
        ]);
    }
}
