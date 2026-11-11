<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Helpers;

use Moudarir\Helpers\EncryptionHelper;
use Moudarir\Helpers\IsHelper;
use Moudarir\Mailer\Exceptions\MailerException;

final class Common
{

    private const array ASCII_SAFE_CHARS = [
        39, 40, 41, 43, 44, 45, 46, 47, 58, 61, 63,
        48, 49, 50, 51, 52, 53, 54, 55, 56, 57,
        65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90,
        97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122
    ];

    public static function prepQEncoding(string $str, string $charset, string $crlf): string
    {
        $str = str_replace(["\r", "\n"], '', $str);
        $iconvEnabled = extension_loaded('iconv');

        if ($iconvEnabled === true) {
            @ini_set('iconv.internal_encoding', $charset);
            $output = @iconv_mime_encode('', $str, [
                'scheme' => 'Q',
                'line-length' => 76,
                'input-charset' => $charset,
                'output-charset' => $charset,
                'line-break-chars' => $crlf
            ]);

            if ($output !== false) {
                return substr($output, 2);
            }

            $chars = iconv_strlen($str, 'UTF-8');
        } elseif (extension_loaded('mbstring') === true) {
            mb_substitute_character('none');
            $chars = mb_strlen($str, 'UTF-8');
        } else {
            $chars = strlen($str);
        }

        $charsetLength = strlen($charset);
        $output = '=?'.$charset.'?Q?';

        for ($i = 0, $length = strlen($output); $i < $chars; $i++) {
            $chr = $iconvEnabled === true
                ? '='.implode('=', str_split(strtoupper(bin2hex(iconv_substr($str, $i, 1, $charset))), 2))
                : '='.strtoupper(bin2hex($str[$i]));

            if ($length + ($l = strlen($chr)) > 74) {
                $output .= '?='.$crlf.' =?'.$charset.'?Q?'.$chr;
                $length = 6 + $charsetLength + $l;
            } else {
                $output .= $chr;
                $length += $l;
            }
        }

        return $output.'?=';
    }

    public static function prepQuotedPrintable(string $str, string $crlf): string
    {
        $str = str_replace(['{unwrap}', '{/unwrap}'], '', $str);

        if ($crlf === "\r\n") {
            return quoted_printable_encode($str);
        }

        $str = preg_replace(['| +|', '/\x00+/'], [' ', ''], $str);

        if (str_contains($str, "\r")) {
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }

        $escape = '=';
        $output = '';

        foreach (explode("\n", $str) as $line) {
            $length = strlen($line);
            $temp = '';

            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                $ascii = ord($char);

                if ($ascii === 32 || $ascii === 9) {
                    if ($i === ($length - 1)) {
                        $char = $escape.sprintf('%02s', dechex($ascii));
                    }
                } elseif ($ascii === 61) {
                    $char = $escape.strtoupper(sprintf('%02s', dechex($ascii)));
                } elseif (!in_array($ascii, self::ASCII_SAFE_CHARS, true)) {
                    $char = $escape.strtoupper(sprintf('%02s', dechex($ascii)));
                }

                if ((strlen($temp) + strlen($char)) >= 76) {
                    $output .= $temp.$escape.$crlf;
                    $temp = '';
                }

                $temp .= $char;
            }

            $output .= $temp.$crlf;
        }

        return substr($output, 0, strlen($crlf) * -1);
    }

    public static function validateEmail(string &$email): bool
    {
        $email = self::cleanEmail($email);

        return IsHelper::validEmail($email);
    }

    /**
     * @throws MailerException
     */
    public static function validateEmails(array|string $emails): array
    {
        $emails = self::stringToArray($emails);

        if (empty($emails)) {
            throw MailerException::invalidEmail();
        }

        return array_map(
            static function ($email) {
                if (self::validateEmail($email) === false) {
                    throw MailerException::invalidEmail($email);
                }

                return $email;
            },
            $emails
        );
    }

    public static function validateEmailForShell(string &$email): bool
    {
        return self::validateEmail($email) &&
            preg_match('#\A[a-z0-9._+-]+@[a-z0-9.-]{1,253}\z#i', $email);
    }

    /**
     * @throws MailerException
     */
    public static function generateBoundary(string $name = ''): string
    {
        if (($binaryBytes = EncryptionHelper::binaryBytes(16)) === null) {
            throw MailerException::generic("Impossible de générer le délimiteur.");
        }

        return $name . bin2hex($binaryBytes);
    }

    public static function cleanEmail(array|string $email): array|string
    {
        if (!is_array($email)) {
            return preg_match('/<([^>]+)>/', $email, $match) ? $match[1] : $email;
        }

        return array_map(
            static fn (string $e): string => preg_match('/<([^>]+)>/', $e, $match) ? $match[1] : $e,
            $email
        );
    }

    public static function stringToArray(array|string $email): array
    {
        if (!is_array($email)) {
            return (str_contains($email, ','))
                ? preg_split('/[\s,]/', $email, -1, PREG_SPLIT_NO_EMPTY)
                : [trim($email)];
        }

        return $email;
    }

    public static function hostname(): string
    {
        if (isset($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        }

        return isset($_SERVER['SERVER_ADDR']) ? '['.$_SERVER['SERVER_ADDR'].']' : '[127.0.0.1]';
    }
}
