<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\PluginBundle\ExeLearning\Service;

/**
 * Short-lived capability for the content endpoint.
 *
 * Asset requests made by the package (relative URLs) carry no course context,
 * so the viewer page, after Chamilo's own course access check, mints a token
 * bound to the user, the package and the content hash. The content endpoint
 * accepts a request only with a valid, unexpired token for the logged-in user.
 */
final class ContentToken
{
    public const int DEFAULT_TTL = 43_200;

    public function __construct(
        private readonly string $secret,
    ) {}

    public function issue(int $userId, int $packageId, string $sha256, int $expires): string
    {
        return $expires.'.'.$this->sign($userId, $packageId, $sha256, $expires);
    }

    public function verify(string $token, int $userId, int $packageId, string $sha256, int $now): bool
    {
        if (1 !== preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $token, $m)) {
            return false;
        }

        $expires = (int) $m[1];
        if ($expires < $now || $userId <= 0) {
            return false;
        }

        return hash_equals($this->sign($userId, $packageId, $sha256, $expires), $m[2]);
    }

    private function sign(int $userId, int $packageId, string $sha256, int $expires): string
    {
        return hash_hmac('sha256', \sprintf('exelearning-content|%d|%d|%s|%d', $userId, $packageId, $sha256, $expires), $this->secret);
    }
}
