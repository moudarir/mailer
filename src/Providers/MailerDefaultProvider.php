<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Providers;

use Moudarir\File\Enum\MimeType;
use Moudarir\Helpers\Datetime\DatetimeHelper;
use Moudarir\Helpers\EncryptionHelper;
use Moudarir\Helpers\IsHelper;
use Moudarir\Mailer\Enums\Transport;
use Moudarir\Mailer\Exceptions\MailerException;
use Moudarir\Mailer\Helpers\Common;
use Moudarir\Mailer\Interfaces\MailerInterface;
use Moudarir\Mailer\MailerConfig;
use Moudarir\Mailer\Messages\MailerDefaultAttachment;
use Moudarir\Mailer\Messages\MailerDefaultMessageBuilder;
use Moudarir\Mailer\Transports\MailerDefaultSMTPTransport;
use Random\RandomException;

class MailerDefaultProvider implements MailerInterface
{

    private string $message_id;

    private string $subject = '';

    private string $alt_message = '';

    private string $body = '';

    private string $final_body = '';

    private string $header_str = '';

    private array $recipients = [];

    private array $cc_array = [];

    private array $bcc_array = [];

    private array $headers = [];

    /**
     * @var array<int, MailerDefaultAttachment>
     */
    private array $attachments = [];

    public function __construct(private readonly MailerConfig $config)
    {
    }

    /**
     * @throws MailerException
     */
    public function from(string $email, string $name = '', ?string $returnPath = null): self
    {
        [$email, $name] = $this->buildAddress($email, $name);

        $this->setHeaders('From', $name.' <'.$email.'>');

        !is_null($returnPath) || $returnPath = $email;
        return $this->setHeaders('Return-Path', '<'.$returnPath.'>');
    }

    /**
     * @throws MailerException
     */
    public function replyTo(string $email, string $name = ''): self
    {
        [$email, $name] = $this->buildAddress($email, $name);

        return $this->setHeaders('Reply-To', $name.' <'.$email.'>');
    }

    /**
     * @throws MailerException
     */
    public function to(array|string $email): self
    {
        $emails = Common::validateEmails($email);

        if ($this->config->transport !== Transport::MAIL) {
            $this->setHeaders('To', implode(', ', $emails));
        }

        $this->recipients = $emails;
        return $this;
    }

    /**
     * @throws MailerException
     */
    public function cc(array|string $email): self
    {
        $emails = Common::validateEmails($email);

        $this->setHeaders('Cc', implode(', ', $emails));

        if ($this->config->transport === Transport::SMTP) {
            $this->cc_array = $emails;
        }

        return $this;
    }

    /**
     * @throws MailerException
     */
    public function bcc(array|string $email): self
    {
        $emails = Common::validateEmails($email);

        if ($this->config->transport === Transport::SMTP) {
            $this->bcc_array = $emails;
        } else {
            $this->setHeaders('Bcc', implode(', ', $emails));
        }

        return $this;
    }

    public function subject(string $subject): self
    {
        return $this->setHeaders(
            'Subject',
            Common::prepQEncoding($subject, $this->config->charset, $this->config->crlf)
        );
    }

    public function html(string $content): self
    {
        $this->body = rtrim(str_replace("\r", '', $content));
        return $this;
    }

    public function text(string $content): self
    {
        $this->alt_message = $content;
        return $this;
    }

    public function body(string $html, string $text): self
    {
        return $this->html($html)->text($text);
    }

    /**
     * @param array{
     *     filepath: string,
     *     filename?: string,
     *     disposition?: string,
     *     mimeType?: MimeType|string|null
     *  }|list<array{
     *     filepath: string,
     *     filename?: string,
     *     disposition?: string,
     *     mimeType?: MimeType|string|null
     *  }> $attachments
     * @throws MailerException
     */
    public function attachments(array $attachments): self
    {
        if ($attachments === []) {
            return $this;
        }

        if (array_filter($attachments, 'is_array') !== $attachments) {
            $attachments = [$attachments];
        }

        foreach ($attachments as $attachment) {
            $this->addAttachment($attachment);
        }

        return $this;
    }

    /**
     * @throws MailerException
     */
    public function send(bool $clearAttachments = true): array
    {
        if (!isset($this->headers['From'])) {
            throw MailerException::missingHeader('From');
        }

        if (
            empty($this->recipients)
            && !isset($this->headers['To'])
            && empty($this->bcc_array)
            && !isset($this->headers['Bcc'])
            && !isset($this->headers['Cc'])
        ) {
            throw MailerException::missingRecipient();
        }

        try {
            $this->addHeaders();

            $result = new MailerDefaultMessageBuilder(
                $this->config,
                $this->attachments,
                $this->headers,
                $this->subject,
                $this->body,
                $this->alt_message
            )->build();

            $this->subject = $result['subject'];
            $this->final_body = $result['body'];
            $this->headers = $result['headers'];
            $this->header_str = $result['headerStr'];

            $success = match ($this->config->transport) {
                Transport::MAIL => $this->sendWithMail(),
                Transport::SENDMAIL => $this->sendWithSendmail(),
                Transport::SMTP => $this->sendWithSmtp(),
            };

            if ($success === false) {
                throw MailerException::sendFailure($this->config->transport);
            }

            return ['MessageId' => $this->message_id];
        } finally {
            $this->clear($clearAttachments);
        }
    }

    public function clear(bool $clearAttachments = false): self
    {
        $this->subject = '';
        $this->body = '';
        $this->alt_message = '';
        $this->final_body = '';
        $this->header_str = '';
        $this->recipients = [];
        $this->cc_array = [];
        $this->bcc_array = [];
        $this->headers = [];

        if ($clearAttachments === true) {
            $this->attachments = [];
        }

        return $this;
    }

    /**
     * @throws MailerException
     */
    private function buildAddress(string $email, string $name = ''): array
    {
        if (Common::validateEmail($email) === false) {
            throw MailerException::invalidEmail($email);
        }

        if (preg_match('/<([^>]+)>/', $email, $match)) {
            $email = $match[1];
        }

        if ($name !== '') {
            $name = !preg_match('/[\200-\377]/', $name)
                ? '"'.addcslashes($name, "\0..\37\177'\"\\").'"'
                : Common::prepQEncoding($name, $this->config->charset, $this->config->crlf);
        }

        return [$email, $name];
    }

    private function sendWithMail(): bool
    {
        $recipients = implode(', ', $this->recipients);
        $from = $this->headers['Return-Path'];

        if (Common::validateEmailForShell($from)) {
            return mail($recipients, $this->subject, $this->final_body, $this->header_str, '-f '.$from);
        }

        return mail($recipients, $this->subject, $this->final_body, $this->header_str);
    }

    /**
     * @throws MailerException
     */
    private function sendWithSendmail(): bool
    {
        if (IsHelper::functionUsable('popen') === false) {
            return false;
        }

        $from = $this->headers['From'];
        $from = Common::validateEmailForShell($from) ? '-f '.$from : '';

        if (($fp = popen($this->config->sendmailPath.' -oi '.$from.' -t', 'w')) === false) {
            return false;
        }

        fwrite($fp, $this->header_str);
        fwrite($fp, $this->final_body);

        $status = pclose($fp);

        if ($status !== 0) {
            throw MailerException::noSendmailSocket($status);
        }

        return true;
    }

    /**
     * @throws MailerException
     */
    private function sendWithSmtp(): bool
    {
        return new MailerDefaultSMTPTransport(
            $this->config,
            $this->final_body,
            $this->header_str,
            $this->headers,
            $this->recipients,
            $this->cc_array,
            $this->bcc_array
        )->send();
    }

    private function setHeaders(array|string $header = [], ?string $value = null): self
    {
        if (is_string($header)) {
            $header = [$header => $value];
        }

        if ($header === []) {
            return $this;
        }

        foreach ($header as $h => $v) {
            $this->headers[$h] = str_replace(["\n", "\r"], '', $v);
        }

        return $this;
    }

    /**
     * @throws MailerException
     */
    private function addHeaders(): void
    {
        try {
            $this->message_id ??= EncryptionHelper::generateToken(6, 'upper', 4);
        } catch (RandomException $exception) {
            throw MailerException::generic("Impossible de générer l'identifiant du message.", $exception);
        }

        $returnPath = str_replace(['>', '<'], '', $this->headers['Return-Path']);

        $this->setHeaders([
            'Date' => DatetimeHelper::now()->toRfc822String(),
            'User-Agent' => $this->config->useragent,
            'X-Sender' => $this->headers['From'],
            'X-Mailer' => $this->config->useragent,
            'X-Priority' => $this->config->priority->value,
            'Message-ID' => '<'.$this->message_id.strstr($returnPath, '@').'>',
            'Mime-Version' => '1.0',
        ]);
    }

    /**
     * @param array{
     *     filepath: string,
     *     filename?: string,
     *     disposition?: string,
     *     mimeType?: MimeType|string|null
     * } $attach
     * @throws MailerException
     */
    private function addAttachment(array $attach): void
    {
        if ($attach === [] || !array_key_exists('filepath', $attach)) {
            return;
        }

        $attachment = MailerDefaultAttachment::add($attach);

        if ($attachment === null) {
            return;
        }

        $this->attachments[] = $attachment;
    }
}
