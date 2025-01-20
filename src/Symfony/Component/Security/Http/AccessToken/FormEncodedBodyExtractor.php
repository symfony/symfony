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
 * Extracts a token from the body request.
 *
 * WARNING!
 * Because of the security weaknesses associated with this method,
 * the request body method SHOULD NOT be used except in application contexts
 * where participating browsers do not have access to the "Authorization" request header field.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6750#section-2.2
 */
final class FormEncodedBodyExtractor implements AccessTokenExtractorInterface
{
    public function __construct(
        private readonly string $parameter = 'access_token',
    ) {
    }

    public function extractAccessToken(Request $request): ?string
    {
        if (
            Request::METHOD_POST !== $request->getMethod()
            || !str_starts_with($request->headers->get('CONTENT_TYPE', ''), 'application/x-www-form-urlencoded')
        ) {
            return null;
        }
        $parameter = $request->request->get($this->parameter);

        return \is_string($parameter) ? $parameter : null;
    }
}
