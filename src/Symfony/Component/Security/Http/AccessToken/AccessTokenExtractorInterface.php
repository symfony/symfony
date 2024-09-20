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
interface AccessTokenExtractorInterface
{
    public function extractAccessToken(Request $request): ?string;
}
