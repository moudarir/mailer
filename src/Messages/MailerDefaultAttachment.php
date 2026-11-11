<?php

declare(strict_types=1);

namespace Moudarir\Mailer\Messages;

use Moudarir\File\Enum\MimeType;
use Moudarir\File\Exceptions\FileResourceException;
use Moudarir\File\Exceptions\MimeDetectionException;
use Moudarir\File\File;
use Moudarir\Mailer\Exceptions\MailerException;

final readonly class MailerDefaultAttachment
{

    private function __construct(
        private string $basename,
        private string $mimeType,
        private string $disposition,
        private string $content,
    )
    {
    }

    /**
     * @throws MailerException
     */
    public static function add(array $data): ?self
    {
        if ($data === [] || !array_key_exists('filepath', $data)) {
            return null;
        }

        $filepath = $data['filepath'];

        if (!str_contains($filepath, '://') && !is_file($filepath)) {
            throw MailerException::missingAttachment($filepath);
        }

        try {
            $mimeType = null;

            if (array_key_exists('mimeType', $data)) {
                $mimeType = is_string($data['mimeType'])
                    ? MimeType::tryFrom($data['mimeType'])
                    : $data['mimeType'];
            }

            $file = File::create($filepath, $mimeType);

            if (($fp = fopen($filepath, 'rb')) === false) {
                throw MailerException::unreadableAttachment($filepath);
            }

            try {
                if (($content = stream_get_contents($fp)) === false) {
                    throw MailerException::unreadableAttachment($filepath);
                }
            } finally {
                fclose($fp);
            }

            $basename = $file->resource()->basename();

            if (array_key_exists('filename', $data)) {
                $filename = trim($data['filename']);

                if ($filename !== '') {
                    $pathInfo = pathinfo($filename);
                    $filename = $pathInfo['filename'] ?? $file->resource()->filename();
                    $extension = $pathInfo['extension'] ?? $file->resource()->extension();

                    $basename = $filename . ($extension !== '' ? '.' . $extension : '');
                }
            }

            if (
                array_key_exists('disposition', $data) &&
                in_array($data['disposition'], ['attachment', 'inline'], true)
            ) {
                $disposition = $data['disposition'];
            } else {
                $disposition = 'attachment';
            }

            return new self(
                $basename,
                $file->detection()->mimeTypeValue(),
                $disposition,
                chunk_split(base64_encode($content)),
            );
        } catch (FileResourceException|MimeDetectionException $exception) {
            throw MailerException::generic($exception->getMessage(), $exception);
        }
    }

    public function append(string $boundary, string $newline): string
    {
        $encodedName = self::encodeBasename($this->basename);

        $nameAttribute = str_starts_with($encodedName, 'UTF-8\'\'') ? 'name*=' : 'name=';
        $filenameAttribute = str_starts_with($encodedName, 'UTF-8\'\'') ? 'filename*=' : 'filename=';

        return '--'.$boundary . $newline
            . 'Content-Type: ' . $this->mimeType . '; ' . $nameAttribute . $encodedName . $newline
            . 'Content-Disposition: ' . $this->disposition . '; ' . $filenameAttribute . $encodedName . $newline
            . 'Content-Transfer-Encoding: base64' . $newline . $newline
            . $this->content . $newline;
    }

    private static function encodeBasename(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);

        if (
            preg_match('/[^\x20-\x7E]/', $value) ||
            str_contains($value, '"') ||
            str_contains($value, '\\')
        ) {
            return "UTF-8''" . rawurlencode($value);
        }

        return '"' . addcslashes($value, '"\\') . '"';
    }
}
