<?php

namespace App\Support;

use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Provides a fallback MIME type guesser when the PHP fileinfo extension
 * is unavailable. This prevents Symfony/Livewire file uploads from
 * failing in environments where MIME detection is not supported.
 */

class FallbackMimeTypeGuesser implements MimeTypeGuesserInterface
{
    public function __construct(
        private readonly ExtensionMimeTypeDetector $detector = new ExtensionMimeTypeDetector,
    ) {}

    public function isGuesserSupported(): bool
    {
        return true;
    }

    public function guessMimeType(string $path): ?string
    {
        return $this->detector->detectMimeTypeFromPath($path) ?? 'application/octet-stream';
    }
}
