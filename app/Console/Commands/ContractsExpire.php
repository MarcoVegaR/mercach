<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Services\ContractServiceInterface;
use Illuminate\Console\Command;

class ContractsExpire extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark overdue signed contracts (VIG/EXT with end_date < today) as VENC';

    public function handle(ContractServiceInterface $service): int
    {
        $count = $service->expireOverdue();
        $this->info("Contracts expired: {$count}");

        return self::SUCCESS;
    }
}
