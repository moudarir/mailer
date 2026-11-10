<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Enums;

enum SmtpCommand: string
{

    case HELO = "EHLO";
    case STARTTLS = "STARTTLS";
    case FROM = "MAIL FROM";
    case TO = "RCPT TO";
    case DATA = "DATA";
    case RESET = "RSET";
    case QUIT = "QUIT";
}
