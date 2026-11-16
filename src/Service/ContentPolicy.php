<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

use const PATHINFO_EXTENSION;

/**
 * Response headers for files served from a package.
 *
 * Package HTML is untrusted author content running on the site origin inside
 * the viewer iframe. These headers are defence in depth, not an isolation
 * boundary (see docs/superpowers/specs, section 4): the boundary is who may
 * upload or edit packages.
 */
final class ContentPolicy
{
    /**
     * HTML documents: scripts allowed (packages need them), nothing else loosened.
     */
    public const string CSP_HTML = "default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; "
        ."img-src 'self' data: blob: https:; media-src 'self' data: blob: https:; "
        ."frame-src 'self' https:; worker-src 'self' blob:; object-src 'none'; base-uri 'self'; "
        ."form-action 'self'; frame-ancestors 'self'";

    /**
     * A package page opened as a top-level document gets an opaque origin.
     */
    public const string CSP_TOP_LEVEL = 'sandbox allow-scripts allow-popups allow-forms allow-downloads';

    /**
     * SVG and XML can carry script: serve them script-free and sandboxed.
     */
    public const string CSP_SCRIPT_FREE = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox";

    private const array MIME_TYPES = [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'json' => 'application/json',
        'txt' => 'text/plain; charset=utf-8',
        'xml' => 'application/xml',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'bmp' => 'image/bmp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'wav' => 'audio/wav',
        'm4a' => 'audio/mp4',
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        'vtt' => 'text/vtt; charset=utf-8',
        'srt' => 'text/plain; charset=utf-8',
        'pdf' => 'application/pdf',
        'wasm' => 'application/wasm',
    ];

    /**
     * Extensions whose content is shown inline but must not run script.
     */
    private const array SCRIPT_FREE = ['svg', 'xml'];

    /**
     * Headers for $path. $topLevel is true when the browser says the request
     * is a top-level navigation (Sec-Fetch-Dest: document).
     *
     * @return array<string, string>
     */
    public static function headersFor(string $path, bool $topLevel): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Cache-Control' => 'private, max-age=3600',
        ];

        $type = self::MIME_TYPES[$extension] ?? null;
        if (null === $type) {
            $headers['Content-Type'] = 'application/octet-stream';
            $headers['Content-Disposition'] = 'attachment';
            $headers['Content-Security-Policy'] = self::CSP_SCRIPT_FREE;

            return $headers;
        }

        $headers['Content-Type'] = $type;

        if (\in_array($extension, self::SCRIPT_FREE, true)) {
            $headers['Content-Security-Policy'] = self::CSP_SCRIPT_FREE;
        } elseif (str_starts_with($type, 'text/html')) {
            $headers['Content-Security-Policy'] = self::CSP_HTML.($topLevel ? '; '.self::CSP_TOP_LEVEL : '');
        }

        return $headers;
    }
}
