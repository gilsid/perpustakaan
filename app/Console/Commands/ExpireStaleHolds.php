<?php

namespace App\Console\Commands;

use App\Services\HoldService;
use Illuminate\Console\Command;

class ExpireStaleHolds extends Command
{
    protected $signature = 'library:expire-holds';

    protected $description = 'Lepaskan hold yang lewat masa ambil dan tawarkan eksemplarnya ke antrean berikutnya';

    public function handle(HoldService $holds): int
    {
        $holds->expireStale();
        $this->info('Hold kedaluwarsa sudah diproses.');

        return self::SUCCESS;
    }
}
