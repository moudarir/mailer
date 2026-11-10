<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Exceptions;

use Exception;
use Moudarir\Mailer\Enums\Protocol;
use Throwable;

final class MailerException extends Exception
{

    public static function sendFailure(Protocol $protocol): self
    {
        return new self($protocol->error());
    }

    public static function missingHeader(string $header): self
    {
        return new self("Impossible d'envoyer un email sans en-tête '$header'.");
    }

    public static function missingRecipient(): self
    {
        return new self("Vous devez spécifier des destinataires: 'To', 'Cc', ou 'Bcc'.");
    }

    public static function missingAttachment(string $file): self
    {
        return new self("Impossible de localiser le fichier joint suivant : $file");
    }

    public static function unreadableAttachment(string $file): self
    {
        return new self("Impossible d'ouvrir ce fichier joint : $file");
    }

    public static function noSmtpHostname(): self
    {
        return new self("Vous n'avez pas spécifié d'hôte SMTP.");
    }

    public static function missingSmtpUserPass(): self
    {
        return new self("Nom d'utilisateur et/ou mot de passe SMTP manquant(s).");
    }

    public static function failedSmtpLogin(string $error): self
    {
        return new self("Erreur lors de l'envoi de la commande AUTH LOGIN : $error");
    }

    public static function errorSmtpOccurred(string $error): self
    {
        return new self("L'erreur SMTP suivante s'est produite : $error");
    }

    public static function errorSmtpAuth(string $error, string $who = 'user'): self
    {
        $who = $who === 'pass' ? 'mot de passe' : "nom d'utilisateur";
        return new self(sprintf("Impossible d'identifier le %s SMTP : %s", $who, $error));
    }

    public static function sendSmtpDataFailed(string $data): self
    {
        return new self("Impossible d'envoyer les données : $data");
    }

    public static function noSendmailSocket(int $status): self
    {
        return new self(sprintf("Impossible d'ouvrir un socket avec Sendmail. Veuillez vérifier la configuration de votre environnement. Code de retour : %d.", $status));
    }

    public static function generic(string $message, ?Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
