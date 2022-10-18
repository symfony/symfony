<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken;

use Symfony\Component\HttpFoundation\Request;

/**
 * Extracts a token from the request header.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6750#section-2.1
 */
final class HeaderAccessTokenExtractor implements AccessTokenExtractorInterface
{
    private string $regex;

    public function __construct(
        private readonly string $headerParameter = 'Authorization',
        private readonly string $tokenType = 'Bearer'
    ) {
        $this->regex = sprintf(
            '/^%s([a-zA-Z0-9\-_\+~\/\.]+)$/',
            '' === $this->tokenType ? '' : preg_quote($this->tokenType).'\s+'
        );
    }

    public function extractAccessToken(Request $request): ?string
    {
        if (!$request->headers->has($this->headerParameter) || !\is_string($header = $request->headers->get($this->headerParameter))) {
            return null;
        }

        if (preg_match($this->regex, $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
