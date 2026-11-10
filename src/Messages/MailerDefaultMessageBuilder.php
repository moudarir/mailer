<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Messages;

use Moudarir\Mailer\Enums\Protocol;
use Moudarir\Mailer\Helpers\Common;
use Moudarir\Mailer\MailerConfig;

final readonly class MailerDefaultMessageBuilder
{

    /**
     * @param MailerConfig $config
     * @param array<int, MailerDefaultAttachment> $attachments
     * @param array $headers
     * @param string $subject
     * @param string $body
     * @param string $altMessage
     */
    public function __construct(
        private MailerConfig $config,
        private array        $attachments,
        private array        $headers,
        private string       $subject,
        private string       $body,
        private string       $altMessage,
    )
    {
    }

    /**
     * @return array{
     *     subject: string,
     *     body: string,
     *     headerStr: string,
     *     headers: array
     * }
     */
    public function build(): array
    {
        $headers = $this->headers;
        $subject = $this->subject;

        if ($this->config->protocol === Protocol::MAIL && isset($headers['Subject'])) {
            $subject = $headers['Subject'];
            unset($headers['Subject']);
        }

        reset($headers);

        $headerContent = $this->writeHeaders($headers);
        $charset = $this->config->charset;
        $newline = $this->config->newline;
        $newlineDouble = str_repeat($newline, 2);
        $hdr = $this->config->protocol === Protocol::MAIL ? $newline : '';
        $message = '';
        $boundary = uniqid('B_ALT_');

        if ($this->attachments === []) {
            $hdr .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $message .= $this->mimeMessage() . $newlineDouble
                . '--' . $boundary . $newline
                . 'Content-Type: text/plain; charset=' . $charset . $newline
                . 'Content-Transfer-Encoding: 8bit' . $newlineDouble
                . $this->altMessage . $newlineDouble
                . '--' . $boundary . $newline
                . 'Content-Type: text/html; charset=' . $charset . $newline
                . 'Content-Transfer-Encoding: quoted-printable' . $newlineDouble
                . Common::prepQuotedPrintable($this->body, $this->config->crlf) . $newlineDouble;

            if ($this->config->protocol === Protocol::MAIL) {
                $headerContent .= $hdr;
            } else {
                $message = $hdr . $newlineDouble . $message;
            }

            $message .= '--'.$boundary.'--';
        } else {
            $atc_boundary = uniqid('B_ATC_');
            $hdr .= 'Content-Type: multipart/mixed; boundary="' . $atc_boundary . '"';

            if ($this->config->protocol === Protocol::MAIL) {
                $headerContent .= $hdr;
            }

            $message .= $this->mimeMessage() . $newlineDouble
                . '--' . $atc_boundary . $newline
                . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . $newlineDouble
                . '--' . $boundary . $newline
                . 'Content-Type: text/plain; charset=' . $charset . $newline
                . 'Content-Transfer-Encoding: 8bit' . $newlineDouble
                . $this->altMessage . $newlineDouble
                . '--' . $boundary . $newline
                . 'Content-Type: text/html; charset=' . $charset . $newline
                . 'Content-Transfer-Encoding: quoted-printable' . $newlineDouble
                . Common::prepQuotedPrintable($this->body, $this->config->crlf) . $newlineDouble
                . '--' . $boundary . '--' . $newlineDouble;

            $message .= $newlineDouble;
            $this->appendAttachments($message, $atc_boundary);

            if ($this->config->protocol !== Protocol::MAIL) {
                $message = $hdr . $newlineDouble . $message;
            }
        }

        $message = preg_replace_callback(
            '/\{unwrap}(.*?)\{\/unwrap}/si',
            [self::class, 'removeNlCallback'],
            $message
        );

        return [
            'subject' => $subject,
            'body' => $message,
            'headerStr' => $headerContent,
            'headers' => $headers
        ];
    }

    private function writeHeaders(array $headers): string
    {
        $header = '';

        foreach ($headers as $key => $val) {
            $val = trim($val);

            if ($val !== '') {
                $header .= $key . ': ' . $val . $this->config->newline;
            }
        }

        return $this->config->protocol === Protocol::MAIL ? rtrim($header) : $header;
    }

    private function appendAttachments(string &$body, string $boundary): void
    {
        if ($this->attachments === []) {
            return;
        }

        foreach ($this->attachments as $attachment) {
            $body .= $attachment->append($boundary, $this->config->newline);
        }

        $body .= '--'.$boundary.'--';
    }

    private static function removeNlCallback(string $matches): string
    {
        $content = $matches[1];

        if (str_contains($content, "\r") || str_contains($content, "\n")) {
            $content = str_replace(["\r\n", "\r", "\n"], '', $content);
        }

        return $content;
    }

    private function mimeMessage(): string
    {
        return 'This is a multi-part message in MIME format.'.$this->config->newline.'Your email application may not support this format.';
    }
}
