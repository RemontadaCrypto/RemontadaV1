<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;

class CustomEmailNotification extends Notification
{
    use Queueable;

    public $title;
    public $line1;
    public $line2;
    public $buttonText;
    public $url;

    public function __construct($title, $line1, $line2 = null, $buttonText = null, $url = null)
    {
        $this->title = $title;
        $this->line1 = $line1;
        $this->line2 = $line2;
        $this->buttonText = $buttonText;
        $this->url = $url;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        if ($this->url){
            return (new MailMessage)
                ->subject($this->title)
                ->greeting('skip default')
                ->line('Hello '.ucfirst($notifiable->name).',')
                ->line(new HtmlString($this->line1))
                ->action($this->buttonText, $this->url)
                ->line(new HtmlString($this->line2));
        }else{
            return (new MailMessage)
                ->subject($this->title)
                ->greeting('skip default')
                ->line('Hello '.ucfirst($notifiable->name).',')
                ->line(new HtmlString($this->line1))
                ->line(new HtmlString($this->line2));
        }
    }
}
