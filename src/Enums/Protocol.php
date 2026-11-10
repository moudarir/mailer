<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Enums;

enum Protocol: string
{

    case MAIL = 'mail';
    case SENDMAIL = 'sendmail';
    case SMTP = 'smtp';

    public function error(): string
    {
        $reason = match ($this) {
            self::MAIL => "la fonction mail() de PHP",
            self::SENDMAIL => "la méthode Sendmail de PHP",
            self::SMTP => "la méthode SMTP de PHP",
        };

        return "Impossible d'envoyer des emails avec $reason. Votre serveur ne doit pas être configuré pour pouvoir utiliser cette méthode.";
    }
}
