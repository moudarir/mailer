<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Interfaces;

use Moudarir\File\Enum\MimeType;
use Moudarir\Mailer\Exceptions\MailerException;

interface MailerInterface
{

    /**
     * @throws MailerException
     */
    public function from(string $email, string $name = '', ?string $returnPath = null): self;

    /**
     * @throws MailerException
     */
    public function replyTo(string $email, string $name = ''): self;

    /**
     * @throws MailerException
     */
    public function to(array|string $email): self;

    /**
     * @throws MailerException
     */
    public function cc(array|string $email): self;

    /**
     * @throws MailerException
     */
    public function bcc(array|string $email): self;

    public function subject(string $subject): self;

    public function html(string $content): self;

    public function text(string $content): self;

    public function body(string $html, string $text): self;

    /**
     * @param array{
     *     filepath: string,
     *     filename?: string,
     *     disposition?: string,
     *     mimeType?: MimeType|string|null
     * }|list<array{
     *     filepath: string,
     *     filename?: string,
     *     disposition?: string,
     *     mimeType?: MimeType|string|null
     * }> $attachments
     * @throws MailerException
     */
    public function attachments(array $attachments): self;

    /**
     * @throws MailerException
     */
    public function send(bool $clearAttachments = true): array;
}
