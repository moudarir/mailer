<?php

declare(strict_types=1);

namespace Moudarir\Mailer;

use Moudarir\File\Enum\MimeType;
use Moudarir\Mailer\Exceptions\MailerException;
use Moudarir\Mailer\Interfaces\MailerInterface;
use Moudarir\Mailer\Providers\MailerDefaultProvider;

final class Mailer
{

    private MailerInterface $provider;

    public function __construct(?MailerInterface $provider = null, ?MailerConfig $config = null)
    {
        $this->provider = $provider !== null
            ? $provider
            : new MailerDefaultProvider($config ?? new MailerConfig());
    }

    public function from(string $email, string $name = '', ?string $returnPath = null): self
    {
        $this->provider->from($email, $name, $returnPath);
        return $this;
    }

    public function to(array|string $email): self
    {
        $this->provider->to($email);
        return $this;
    }

    public function cc(array|string $email): self
    {
        $this->provider->cc($email);
        return $this;
    }

    public function bcc(array|string $email): self
    {
        $this->provider->bcc($email);
        return $this;
    }

    public function replyTo(string $email, string $name = ''): self
    {
        $this->provider->replyTo($email, $name);
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->provider->subject($subject);
        return $this;
    }

    public function html(string $content): self
    {
        $this->provider->html($content);
        return $this;
    }

    public function text(string $content): self
    {
        $this->provider->text($content);
        return $this;
    }

    public function body(string $html, string $text): self
    {
        $this->provider->body($html, $text);
        return $this;
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
        $this->provider->attachments($attachments);
        return $this;
    }

    /**
     * @throws MailerException
     */
    public function send(bool $clearAttachments = true): array
    {
        return $this->provider->send($clearAttachments);
    }
}
