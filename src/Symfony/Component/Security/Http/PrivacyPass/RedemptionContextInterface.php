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
 * Determines the context the tokens of a challenge are bound to.
 *
 * An origin that binds its tokens to a context has to rebuild that same context when a token comes back, since the token carries the digest of the challenge it answers and nothing else.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.1.1.2
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
interface RedemptionContextInterface
{
    /**
     * The contexts a token is accepted for, the one to challenge with first.
     *
     * @return list<string> Each either empty or 32 bytes long
     */
    public function getAcceptedContexts(Request $request): array;

    /**
     * How long a challenge carrying one of these contexts stays acceptable, in seconds, or null when it never stops being acceptable.
     *
     * An origin advertises it as the "max-age" of its challenges, so that a client does not spend a token on a challenge this origin has already forgotten.
     */
    public function getLifetime(): ?int;
}
