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
 * Extracts a token from a query string parameter.
 *
 * WARNING!
 * Because of the security weaknesses associated with the URI method,
 * including the high likelihood that the URL containing the access token will be logged,
 * it SHOULD NOT be used unless it is impossible to transport the access token in the
 * request header field.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6750#section-2.3
 */
final class QueryAccessTokenExtractor implements AccessTokenExtractorInterface
{
    public const PARAMETER = 'access_token';

    public function __construct(
        private readonly string $parameter = self::PARAMETER,
    ) {
    }

    public function extractAccessToken(Request $request): ?string
    {
        $parameter = $request->query->get($this->parameter);

        return \is_string($parameter) ? $parameter : null;
    }
}
