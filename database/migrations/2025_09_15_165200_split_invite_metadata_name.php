<?php

use App\Models\Invite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Update existing invites: split metadata.name into metadata.first_name and metadata.last_name
        // Only process rows that still have a non-empty metadata->name
        DB::table('invites')
            ->select(['id', 'metadata'])
            ->whereNotNull('metadata->name')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $meta = is_array($row->metadata) ? $row->metadata : (json_decode($row->metadata, true) ?: []);
                    $full = trim((string)($meta['name'] ?? ''));
                    if ($full === '') {
                        continue;
                    }

                    // Simple split heuristic: first token -> first_name, remainder -> last_name
                    $first = trim(strtok($full, ' '));
                    $rest = trim(substr($full, strlen($first)));
                    $last = $rest !== '' ? $rest : '';

                    // Preserve existing first_name/last_name if already set; otherwise set from split
                    if (empty($meta['first_name'])) {
                        $meta['first_name'] = $first;
                    }
                    if (empty($meta['last_name'])) {
                        $meta['last_name'] = $last;
                    }

                    // Remove legacy name key to avoid ambiguity
                    unset($meta['name']);

                    DB::table('invites')->where('id', $row->id)->update([
                        'metadata' => $meta,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Best-effort rollback: combine first_name and last_name back to name (do not remove first/last)
        DB::table('invites')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $meta = is_array($row->metadata) ? $row->metadata : (json_decode($row->metadata, true) ?: []);
                    $first = trim((string)($meta['first_name'] ?? ''));
                    $last = trim((string)($meta['last_name'] ?? ''));
                    $combined = trim($first . ' ' . $last);
                    if ($combined === '') {
                        continue;
                    }
                    $meta['name'] = $combined;
                    DB::table('invites')->where('id', $row->id)->update([
                        'metadata' => $meta,
                    ]);
                }
            });
    }
};
