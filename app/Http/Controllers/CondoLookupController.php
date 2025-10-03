<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Local;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CondoLookupController extends Controller
{
    /**
     * GET /condo/lookup/locals?market_id=1&q=ABC&limit=20
     * Quick JSON lookup for Locals by market and optional search query.
     */
    public function locals(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'market_id' => ['required', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $marketId = (int) $validated['market_id'];
        $q = (string) ($validated['q'] ?? '');
        $limit = (int) ($validated['limit'] ?? 100);

        $query = Local::query()
            ->where('market_id', $marketId)
            ->where('is_active', true);

        if ($q !== '') {
            // Normalize input to ASCII and lowercase
            $qAscii = Str::ascii(mb_strtolower(trim($q)));
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $qAscii).'%';
            // Normalize DB column: strip accents and lowercase via translate+lower, CODE ONLY
            $normalized = "translate(lower(%s), 'áàäâãåéèëêíìïîóòöôõúùüûñçÁÀÄÂÃÅÉÈËÊÍÌÏÎÓÒÖÔÕÚÙÜÛÑÇ', 'aaaaaaeeeeiiiiooooouuuuncaaaaaaeeeeiiiiooooouuuunc')";
            $query->whereRaw(sprintf($normalized, 'code').' LIKE ?', [$like]);
        }

        $items = $query
            ->orderBy('code')
            ->limit($limit)
            ->get(['id', 'code', 'name'])
            ->map(fn ($l) => [
                'id' => (int) $l->getAttribute('id'),
                'code' => (string) ($l->getAttribute('code') ?? ''),
                'name' => (string) ($l->getAttribute('name') ?? ''),
            ])->values();

        return response()->json([
            'items' => $items,
        ]);
    }
}
