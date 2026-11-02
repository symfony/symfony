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
 * Binds the tokens to no context, which lets clients fetch them ahead of time and cache them.
 *
 * The tokens of every origin sharing this issuer then answer this challenge, so the state against double spending has to be shared by all of them, and any of them can drain the tokens of a client.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9577#section-5.4
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class EmptyRedemptionContext implements RedemptionContextInterface
{
    public function getAcceptedContexts(Request $request): array
    {
        return [''];
    }

    public function getLifetime(): ?int
    {
        return null;
    }
}
