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
 * ImpersonatedUserTokenInterface is the interface for an impersonated user token.
 *
 * @author Pierre-Charles Bertineau <pc.bertineau@alterphp.com>
 */
interface ImpersonatedUserTokenInterface extends TokenInterface
{
    /**
     * Provides original token if available.
     */
    public function getOriginalToken(): ?TokenInterface;
}
