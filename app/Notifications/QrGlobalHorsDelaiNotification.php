<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class QrGlobalHorsDelaiNotification extends Notification
{
    use Queueable;

    protected $heureLimite;
    protected $heureArrivee;

    public function __construct(string $heureLimite, string $heureArrivee)
    {
        $this->heureLimite = $heureLimite;
        $this->heureArrivee = $heureArrivee;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => "Arrivée enregistrée à {$this->heureArrivee}, après l'heure limite ({$this->heureLimite}). Vous êtes marqué en retard.",
        ];
    }
}