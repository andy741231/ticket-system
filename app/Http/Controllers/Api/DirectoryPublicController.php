<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DirectoryPublicController extends Controller
{
    /**
     * Publicly accessible JSON API for directory_team data.
     * Supports optional query params:
     * - group: filter by group_1 (e.g., leadership, team, external advisory board)
     * - team: filter by team (e.g., iaphs)
     * - q: search by first_name/last_name/description
     */
    public function index(Request $request)
    {
        $group = $request->query('group');
        $team = $request->query('team');
        $q = $request->query('q');

        $query = Team::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'degree',
                'title',
                'email',
                'description',
                'message',
                'bio',
                'img',
                'group_1',
                'team',
            ])
            ->when($group, fn ($qq) => $qq->where('group_1', $group))
            ->when($team, fn ($qq) => $qq->where('team', $team))
            ->when($q, function ($qq, $q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('first_name', 'like', "%{$q}%")
                      ->orWhere('last_name', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name');

        $records = $query->get()->map(function (Team $t) {
            return [
                'id' => (string) $t->id,
                'name' => trim(($t->first_name ?? '') . ' ' . ($t->last_name ?? '')),
                'degree' => $t->degree,
                'title' => $t->title,
                'email' => $t->email,
                'description' => $t->description ?? '',
                'message' => $t->message ?? '',
                'bio' => $t->bio ?? '',
                'img' => $this->absoluteImageUrl($t->img),
                'group' => $t->group_1,
                'team' => $t->team,
            ];
        });

        return response()->json($records)->withHeaders($this->corsHeaders());
    }

    /**
     * Build absolute URL for image if a relative path is stored.
     */
    private function absoluteImageUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        // Normalize leading slash
        $normalized = Str::startsWith($path, '/') ? $path : ('/' . ltrim($path, '/'));
        return rtrim(config('app.url'), '/') . $normalized;
    }

    /**
     * Standard CORS headers for public API.
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Vary' => 'Origin',
        ];
    }

    /**
     * Handle CORS preflight explicitly when needed.
     */
    public function options(): \Illuminate\Http\Response
    {
        return response('', 204)->withHeaders($this->corsHeaders());
    }
}
