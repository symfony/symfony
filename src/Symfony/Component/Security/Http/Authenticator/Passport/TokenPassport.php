<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport;

use LogicException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\TokenBadge;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 *
 * @final
 */
class TokenPassport extends SelfValidatingPassport
{
    public function __construct(TokenBadge $tokenBadge, array $badges = [])
    {
        parent::__construct($tokenBadge, $badges);
    }

    public function getUser(): UserInterface
    {
        if (null === $this->user) {
            if (!$this->hasBadge(TokenBadge::class)) {
                throw new LogicException('Cannot get the Security user, no username or TokenBadge configured for this passport.');
            }

            /* @phpstan-ignore-next-line */
            $this->user = $this->getBadge(TokenBadge::class)->getUser();
        }

        return $this->user;
    }
}
