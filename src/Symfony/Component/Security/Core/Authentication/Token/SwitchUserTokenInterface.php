<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token;

/**
 * SwitchUserTokenInterface is the interface for a switch user token.
 *
 * @author Pierre-Charles Bertineau <pc.bertineau@alterphp.com>
 */
interface SwitchUserTokenInterface extends TokenInterface
{
    public function getOriginalToken(): ?TokenInterface;

    public function getSwitchingAdditionalRole(): string;
}
