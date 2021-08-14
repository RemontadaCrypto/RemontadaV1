<?php

namespace App\Jobs;

use App\Http\Controllers\TradeController;
use App\Http\Controllers\TransactionController;
use App\Models\Trade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SettleTradeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Trade
     */
    private $trade;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Trade $trade)
    {
        $this->trade = $trade;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        TradeController::sendCoinToBuyer($this->trade);
        TradeController::sendFeeToAdmin($this->trade);
    }
}
