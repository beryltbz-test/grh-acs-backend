<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;

class BienvenuNotification extends Notification
{
    public function via($notifiable) { return ['database']; }

    public function toDatabase($notifiable)
    {
        return [
            'type'    => 'bienvenu',
            'message' => "Bienvenue sur GRH ACS ! Votre compte a été créé avec succès.",
        ];
    }
} 