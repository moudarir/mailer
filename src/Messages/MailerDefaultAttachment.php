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
        public string $name,
        public string $mimeType,
        public string $disposition,
        public string $content,
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

            return new self(
                $data['filename'] ?? $file->resource()->basename(),
                    $file->detection()->mimeTypeValue(),
                    $data['disposition'] ?? 'attachment',
                    chunk_split(base64_encode($content)),
            );
        } catch (FileResourceException|MimeDetectionException $exception) {
            throw MailerException::generic($exception->getMessage(), $exception);
        }
    }

    public function append(string $boundary, string $newline): string
    {
        return '--'.$boundary . $newline
            . 'Content-Type: ' . $this->mimeType . '; name="' . $this->name . '"' . $newline
            . 'Content-Disposition: ' . $this->disposition . ';' . $newline
            . 'Content-Transfer-Encoding: base64' . $newline . $newline
            . $this->content . $newline;
    }
}
