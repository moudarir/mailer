# MOUDARIR Mailer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/moudarir/mailer.svg?style=flat-square)](https://packagist.org/packages/moudarir/mailer)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/moudarir/mailer/php?color=%238892bf&style=flat-square)](https://packagist.org/packages/moudarir/mailer)
[![License](https://img.shields.io/packagist/l/moudarir/mailer.svg?style=flat-square)](https://packagist.org/packages/moudarir/mailer)

**moudarir/mailer** is a modern, dependency-light, and robust PHP package designed to handle email transmissions seamlessly. Whether you are working on a minimalist legacy project or a modern application, it provides a unified API to dispatch emails through multiple transport layers.

## Key Features

- **Multiple Transport Drivers:** Switch effortlessly between different sending mechanisms:
    - **PHP `mail()`**: Native PHP mail function for standard setups.
    - **Sendmail**: Direct integration with local Sendmail binaries.
    - **SMTP**: Fully configurable SMTP transport supporting secure connections (TLS/SSL) and authentication.
- **Modern PHP:** Built with strict types and optimized for PHP 8.4+.
- **RFC-Compliant:** Proper handling of headers, encodings (like Q-encoding), and multi-part messages.
- **Zero Bloat:** Minimal external dependencies for maximum performance and security.

## Requirements

- PHP 8.4+
- `ext-iconv` extension

## Installation

```bash
composer require moudarir/mailer
```

## Usage

### Native PHP mail function

Default:

```php
<?php

use Moudarir\Mailer\Mailer;
use Moudarir\Mailer\Exceptions\MailerException;

try {
    new Mailer()
        ->from('no-reply@domain.com', 'No Reply')
        ->to('destinator@domain.com')
        ->subject($subject)
        ->body($html, $plain)
        ->send();
} catch (MailerException $exception) {
    error_log($exception->getMessage());
}
```

With attachment:

```php
<?php

use Moudarir\Mailer\Mailer;
use Moudarir\Mailer\Exceptions\MailerException;

try {
    new Mailer()
        ->from('no-reply@domain.com', 'No Reply')
        ->to('destinator@domain.com')
        ->subject($subject)
        ->body($html, $plain)
        ->attachments(['filepath' => '/path/to/picture.jpg'])
        ->send();
} catch (MailerException $exception) {
    error_log($exception->getMessage());
}
```

With multiple attachments:

```php
<?php

use Moudarir\File\Enum\MimeType;
use Moudarir\Mailer\Mailer;
use Moudarir\Mailer\Exceptions\MailerException;

try {
    new Mailer()
        ->from('no-reply@domain.com', 'No Reply')
        ->to('destinator@domain.com')
        ->subject($subject)
        ->body($html, $plain)
        ->attachments([
            ['filepath' => '/path/to/picture.jpg'],
            ['filepath' => '/path/to/document.docx', 'filename' => 'My document.docx'],
            ['filepath' => '/path/to/archive.zip', 'mimeType' => MimeType::ZIP],
        ])
        ->send();
} catch (MailerException $exception) {
    error_log($exception->getMessage());
}
```

### Using local Sendmail binaries

```php
<?php

use Moudarir\Mailer\Mailer;
use Moudarir\Mailer\MailerConfig;
use Moudarir\Mailer\Enums\Transport;
use Moudarir\Mailer\Exceptions\MailerException;

try {
    new Mailer(config: new MailerConfig(transport: Transport::SENDMAIL, sendmailPath: '/usr/sbin/sendmail'))
        ->from('no-reply@domain.com', 'No Reply')
        ->to('destinator@domain.com')
        ->subject($subject)
        ->body($html, $plain)
        ->send();
} catch (MailerException $exception) {
    error_log($exception->getMessage());
}
```

### Using SMTP transport

```php
<?php

use Moudarir\Mailer\Mailer;
use Moudarir\Mailer\MailerConfig;
use Moudarir\Mailer\Enums\Transport;
use Moudarir\Mailer\Exceptions\MailerException;

try {
    $config = new MailerConfig(
        transport: Transport::SMTP,
        smtpHost: 'smtp.mailserver.com',
        smtpUser: 'username@domain.com',
        smtpPass: 'your-password',
        smtpPort: 587,
        smtpCrypto: 'tls',
    );
    new Mailer(config: $config)
        ->from('no-reply@domain.com', 'No Reply')
        ->to('destinator@domain.com')
        ->subject($subject)
        ->body($html, $plain)
        ->send();
} catch (MailerException $exception) {
    error_log($exception->getMessage());
}
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).