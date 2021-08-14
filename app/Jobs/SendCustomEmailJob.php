<?php

namespace App\Jobs;

use App\Http\Controllers\MailController;
use App\Models\User;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCustomEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var User
     */
    private $user;
    private $type;
    private $payload;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, $type, $payload = null)
    {
        $this->user = $user;
        $this->type = $type;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        switch ($this->type) {
            case 'withdrawal' :
                MailController::sendWithdrawalTransactionMail($this->user, $this->payload);
                break;
            case 'buyer' :
                MailController::sendBuyerTransactionMail($this->user, $this->payload);
                break;
            case 'seller' :
                MailController::sendSellerTransactionMail($this->user, $this->payload);
                break;
        }
    }
}
