<?php

namespace App\Jobs;

use App\Mail\TextNotification;
use App\Models\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendEmail extends Job
{
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Notifiction object
     * 
     * @var Notification
     */
    private Notification $notification;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $notification = $this->notification;

        // Se a notification tiver sido excluída, não preciso envia-la
        if ($notification->deleted_at) {
            return;
        }

        // Se a notificação já tiver sido enviada, não a envio novamente
        if ($notification->sended || $notification->sended_at) {
            return true;
        }

        // Envio-a por e-mail
        Mail::send([], [], function($message) use ($notification) {
            $message->to($notification->mail_to)
                ->subject('Challenge - Notificação')
                ->setBody($notification->content);
        });

        // E marco a notificação como enviada
        $notification->update(['sended' => true, 'sended_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}
