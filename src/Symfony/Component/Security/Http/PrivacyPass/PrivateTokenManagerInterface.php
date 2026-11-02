<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\PrivacyPass;

use Symfony\Component\HttpFoundation\Request;

/**
 * Challenges a request for a Privacy Pass token, and tells whether the one it carries is acceptable.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
interface PrivateTokenManagerInterface
{
    /**
     * Whether the request carries a token this origin accepts, and spends it.
     *
     * The client is not told which check failed: a token that is absent, malformed, unsigned, bound to another context or already spent all lead to the same challenge.
     */
    public function isTokenValid(Request $request): bool;

    /**
     * The value of the "WWW-Authenticate" header of the response asking for a token.
     */
    public function getChallengeHeader(Request $request): string;
}
