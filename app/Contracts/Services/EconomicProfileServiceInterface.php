<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use DateTimeInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface EconomicProfileServiceInterface
{
    /**
     * Autosuggest concessionaires by name or document.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchConcessionaires(string $q, int $limit = 20): array;

    /**
     * Autosuggest locals by code or name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchLocals(string $q, int $limit = 20): array;

    /**
     * Economic profile for a concessionaire at a given date.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forConcessionaire(int $id, ?DateTimeInterface $at = null, array $filters = []): array;

    /**
     * Economic profile for a local at a given date.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forLocal(int $id, ?DateTimeInterface $at = null, array $filters = []): array;

    /**
     * Export profile as CSV or JSON.
     *
     * @param  array<string, mixed>  $filters
     */
    public function export(string $scope, int $id, string $format, ?DateTimeInterface $at = null, array $filters = []): StreamedResponse;

    /**
     * Payment history for a concessionaire, optionally filtered by local IDs.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function paymentHistoryForConcessionaire(int $id, ?DateTimeInterface $at = null, array $filters = []): array;

    /**
     * Payment history for a local.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function paymentHistoryForLocal(int $id, ?DateTimeInterface $at = null, array $filters = []): array;
}
