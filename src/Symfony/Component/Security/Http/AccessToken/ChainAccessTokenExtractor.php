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
 * The token extractor retrieves the token from a request.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class ChainAccessTokenExtractor implements AccessTokenExtractorInterface
{
    /**
     * @param AccessTokenExtractorInterface[] $accessTokenExtractors
     */
    public function __construct(
        private readonly iterable $accessTokenExtractors,
    ) {
    }

    public function extractAccessToken(Request $request): ?string
    {
        foreach ($this->accessTokenExtractors as $extractor) {
            if ($accessToken = $extractor->extractAccessToken($request)) {
                return $accessToken;
            }
        }

        return null;
    }
}
