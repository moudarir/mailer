<?php

declare(strict_types=1);

namespace Moudarir\Mailer;

use Moudarir\Mailer\Enums\Transport;

final readonly class MailerConfig
{

    public function __construct(
        public Transport $transport = Transport::MAIL,
        public string    $useragent = "Moudarir-Mailer",
        public string    $sendmailPath = "/usr/sbin/sendmail",
        public string    $smtpHost = "127.0.0.1",
        public ?string   $smtpUser = null,
        public ?string   $smtpPass = null,
        public int       $smtpPort = 1025,
        public int       $smtpTimeout = 5,
        public bool      $smtpKeepAlive = false,
        public string    $smtpCrypto = "", // 'tls', 'ssl' or ''
        public string    $charset = "UTF-8",
        public string $crlf = "\r\n",
        public string $newline = "\r\n",
    )
    {
    }
}
