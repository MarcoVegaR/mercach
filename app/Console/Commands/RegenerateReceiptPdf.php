<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Receipt;
use App\Services\ReceiptPdfGenerator;
use Illuminate\Console\Command;

class RegenerateReceiptPdf extends Command
{
    protected $signature = 'receipts:regenerate
                            {--id= : Receipt ID to regenerate (omit to regenerate all ACTIVE receipts missing PDF)}
                            {--payment= : Payment ID — regenerates the ACTIVE PAYMENT-scope receipt for that payment}
                            {--force : Regenerate even if PDF already exists}';

    protected $description = 'Regenerate PDF for one or all receipts. Use --id or --payment to target a specific receipt.';

    public function handle(ReceiptPdfGenerator $generator): int
    {
        $receipts = $this->resolveReceipts();

        if ($receipts->isEmpty()) {
            $this->warn('No receipts found matching the given criteria.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $ok = 0;
        $fail = 0;

        foreach ($receipts as $receipt) {
            $num = (string) $receipt->getAttribute('receipt_number');

            if (! $force && ! empty($receipt->getAttribute('pdf_path'))) {
                $this->line("  skip  {$num} (PDF already exists, use --force to overwrite)");

                continue;
            }

            try {
                $result = $generator->render($receipt);
                $receipt->fill([
                    'pdf_path' => $result['pdf_path'],
                    'pdf_sha256' => $result['pdf_sha256'],
                    'rendered_at' => $result['rendered_at'],
                ])->save();
                $this->info("  ok    {$num} → {$result['pdf_path']}");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("  fail  {$num}: {$e->getMessage()}");
                $fail++;
            }
        }

        $this->newLine();
        $this->line("Done. Regenerated: {$ok}  Failed: {$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Receipt>
     */
    private function resolveReceipts(): \Illuminate\Database\Eloquent\Collection
    {
        $id = $this->option('id');
        $paymentId = $this->option('payment');

        if ($id !== null) {
            return Receipt::query()
                ->where('id', (int) $id)
                ->get();
        }

        if ($paymentId !== null) {
            return Receipt::query()
                ->where('payment_id', (int) $paymentId)
                ->where('status', 'ACTIVE')
                ->where(function ($q) {
                    $q->where('scope', 'PAYMENT')->orWhereNull('scope');
                })
                ->orderByDesc('id')
                ->limit(1)
                ->get();
        }

        return Receipt::query()
            ->where('status', 'ACTIVE')
            ->whereNull('pdf_path')
            ->get();
    }
}
