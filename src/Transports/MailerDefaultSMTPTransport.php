<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Transports;

use Moudarir\Mailer\Enums\SmtpCommand;
use Moudarir\Mailer\Exceptions\MailerException;
use Moudarir\Mailer\Helpers\Common;
use Moudarir\Mailer\MailerConfig;

final class MailerDefaultSMTPTransport
{

    /**
     * @var resource|false
     */
    private $stream = false;

    public function __construct(
        private readonly MailerConfig $config,
        private readonly string $body,
        private readonly string $headerStr,
        private readonly array $headers,
        private readonly array $recipients,
        private readonly array $cc,
        private readonly array $bcc,
    ) {
    }

    /**
     * Ferme la connexion si elle est encore ouverte.
     *
     * Aucune commande SMTP ne doit être exécutée dans le destructeur.
     */
    public function __destruct()
    {
        $this->closeStream();
    }

    /**
     * @throws MailerException
     */
    public function send(): bool
    {
        try {
            if ($this->config->smtpHost === '') {
                throw MailerException::noSmtpHostname();
            }

            $this->connect();
            $this->authenticate();

            $this->command(SmtpCommand::FROM, $this->headers['From']);

            foreach ($this->recipients as $recipient) {
                $this->command(SmtpCommand::TO, $recipient);
            }

            foreach ($this->cc as $recipient) {
                if ($recipient !== '') {
                    $this->command(SmtpCommand::TO, $recipient);
                }
            }

            foreach ($this->bcc as $recipient) {
                if ($recipient !== '') {
                    $this->command(SmtpCommand::TO, $recipient);
                }
            }

            $this->command(SmtpCommand::DATA);

            $this->sendMessageData();

            $reply = $this->getData();

            if (!str_starts_with($reply, '250')) {
                throw MailerException::errorSmtpOccurred($reply);
            }

            $this->terminate();

            return true;
        } finally {
            $this->closeStream();
        }
    }

    /**
     * Envoie le message complet après la commande DATA.
     *
     * Les en-têtes et le corps sont traités ensemble pour appliquer
     * correctement le dot-stuffing.
     *
     * @throws MailerException
     */
    private function sendMessageData(): void
    {
        $message = $this->headerStr . $this->body;

        $message = str_replace(["\r\n", "\r"], "\n", $message);

        $message = str_replace("\n", "\r\n", $message);

        $message = preg_replace('/(^|\r\n)\./', '$1..', $message) ?? $message;

        if (!str_ends_with($message, "\r\n")) {
            $message .= "\r\n";
        }

        $this->writeData($message, false);

        $this->writeData('.');
    }

    /**
     * Termine la session SMTP.
     *
     * @throws MailerException
     */
    private function terminate(): void
    {
        try {
            if (is_resource($this->stream)) {
                $this->command(SmtpCommand::QUIT);
            }
        } finally {
            $this->closeStream();
        }
    }

    private function closeStream(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = false;
    }

    /**
     * Établit la connexion SMTP.
     *
     * @throws MailerException
     */
    private function connect(): void
    {
        if (is_resource($this->stream)) {
            return;
        }

        $ssl = $this->config->smtpCrypto === 'ssl'
            ? 'ssl://'
            : '';

        $this->stream = fsockopen(
            $ssl . $this->config->smtpHost,
            $this->config->smtpPort,
            $errno,
            $err_msg,
            $this->config->smtpTimeout
        );

        if (!is_resource($this->stream)) {
            throw MailerException::errorSmtpOccurred($errno . ' ' . $err_msg);
        }

        stream_set_timeout($this->stream, $this->config->smtpTimeout);

        // Le serveur doit annoncer qu'il est prêt avant toute commande.
        $reply = $this->getData();

        if (!str_starts_with($reply, '220')) {
            throw MailerException::errorSmtpOccurred($reply);
        }

        // Présente le client au serveur et récupère ses capacités.
        $this->command(SmtpCommand::HELO);

        if ($this->config->smtpCrypto === 'tls') {
            // Demande le passage à une connexion TLS.
            $this->command(SmtpCommand::STARTTLS);

            $crypto = stream_socket_enable_crypto(
                $this->stream,
                true,
                STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                | STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($crypto !== true) {
                $error = error_get_last();

                throw MailerException::errorSmtpOccurred(
                    'Impossible d\'établir la connexion TLS.'
                    . ($error !== null ? ' ' . $error['message'] : '')
                );
            }

            // Les capacités doivent être récupérées à nouveau après TLS.
            $this->command(SmtpCommand::HELO);
        }
    }

    /**
     * Authentifie le client auprès du serveur SMTP.
     *
     * @throws MailerException
     */
    private function authenticate(): void
    {
        $user = $this->config->smtpUser;
        $pass = $this->config->smtpPass;

        // Aucun identifiant fourni : authentification non nécessaire.
        if ($user === null && $pass === null) {
            return;
        }

        // Une configuration partielle ne doit jamais désactiver
        // silencieusement l'authentification.
        if ($user === null || $pass === null || $user === '' || $pass === '') {
            throw MailerException::missingSmtpUserPass();
        }

        $this->writeData('AUTH LOGIN');

        $reply = $this->getData();

        // Le serveur indique que la session est déjà authentifiée.
        if (str_starts_with($reply, '503')) {
            return;
        }

        if (!str_starts_with($reply, '334')) {
            throw MailerException::failedSmtpLogin($reply);
        }

        // Envoie le nom d'utilisateur encodé en Base64.
        $this->writeData(base64_encode($user));

        $reply = $this->getData();

        if (!str_starts_with($reply, '334')) {
            throw MailerException::errorSmtpAuth($reply);
        }

        // Envoie le mot de passe encodé en Base64.
        $this->writeData(base64_encode($pass));

        $reply = $this->getData();

        if (!str_starts_with($reply, '235')) {
            throw MailerException::errorSmtpAuth($reply, 'pass');
        }
    }

    /**
     * Exécute une commande SMTP et vérifie sa réponse.
     *
     * @throws MailerException
     */
    private function command(SmtpCommand $command, string $data = ''): void
    {
        $expectedResponse = null;

        switch ($command) {
            case SmtpCommand::HELO:
                $this->writeData($command->value . ' ' . Common::hostname());

                $expectedResponse = 250;
                break;
            case SmtpCommand::STARTTLS:
                $this->writeData($command->value);

                $expectedResponse = 220;
                break;
            case SmtpCommand::TO:
            case SmtpCommand::FROM:
                $this->writeData($command->value . ':<' . $data . '>');

                $expectedResponse = 250;
                break;
            case SmtpCommand::DATA:
                $this->writeData($command->value);

                $expectedResponse = 354;
                break;
            case SmtpCommand::RESET:
                $this->writeData($command->value);

                $expectedResponse = 250;
                break;
            case SmtpCommand::QUIT:
                $this->writeData($command->value);

                $expectedResponse = 221;
                break;
        }

        $reply = $this->getData();

        if ((int) substr($reply, 0, 3) !== $expectedResponse) {
            throw MailerException::errorSmtpOccurred($reply);
        }
    }

    /**
     * Lit une réponse SMTP complète, y compris les réponses multilignes.
     *
     * @throws MailerException
     */
    private function getData(): string
    {
        if (!is_resource($this->stream)) {
            throw MailerException::errorSmtpOccurred('La connexion SMTP est fermée.');
        }

        $data = '';
        $responseCode = null;

        while (true) {
            $line = fgets($this->stream, 515);

            if ($line === false) {
                $meta = stream_get_meta_data($this->stream);

                if ($meta['timed_out'] === true) {
                    throw MailerException::errorSmtpOccurred('SMTP timeout.');
                }

                throw MailerException::errorSmtpOccurred(
                    'Connexion SMTP interrompue.'
                    . ($data !== '' ? ' Réponse reçue : ' . $data : '')
                );
            }

            $data .= $line;

            $responseLine = rtrim($line, "\r\n");

            if (!preg_match('/^([0-9]{3})([ -])/', $responseLine, $matches)) {
                throw MailerException::errorSmtpOccurred('Réponse SMTP invalide : ' . $responseLine);
            }

            if ($responseCode === null) {
                $responseCode = $matches[1];
            } elseif ($responseCode !== $matches[1]) {
                throw MailerException::errorSmtpOccurred('Code de réponse SMTP incohérent : ' . $data);
            }

            // Un espace après le code indique la dernière ligne.
            if ($matches[2] === ' ') {
                break;
            }
        }

        return $data;
    }

    /**
     * Écrit des données dans le socket SMTP.
     *
     * @throws MailerException
     */
    private function writeData(string $data, bool $appendNewline = true): void
    {
        if (!is_resource($this->stream)) {
            throw MailerException::sendSmtpDataFailed($data);
        }

        if ($appendNewline === true) {
            $data .= "\r\n";
        }

        $length = strlen($data);
        $written = 0;
        $timestamp = null;

        while ($written < $length) {
            $result = fwrite($this->stream, substr($data, $written));

            if ($result === false) {
                throw MailerException::sendSmtpDataFailed($data);
            }

            if ($result === 0) {
                if ($timestamp === null) {
                    $timestamp = microtime(true);
                } elseif (microtime(true) - $timestamp >= $this->config->smtpTimeout) {
                    throw MailerException::sendSmtpDataFailed($data);
                }

                usleep(250_000);
                continue;
            }

            $written += $result;
            $timestamp = null;
        }
    }
}
