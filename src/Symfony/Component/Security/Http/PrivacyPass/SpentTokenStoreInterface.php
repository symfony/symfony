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

/**
 * Keeps the tokens an origin already accepted, so that none of them is redeemed twice.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-2.2.2
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
interface SpentTokenStoreInterface
{
    /**
     * Records a token as spent, and tells whether it had not been spent before.
     *
     * A token has to be remembered for as long as the challenge it answers is accepted, and the record has to be taken by one caller only, since the point is to let a token through once.
     */
    public function spend(PrivateToken $token): bool;
}
