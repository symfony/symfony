<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * This class is used when the authenticator system is activated.
 *
 * This is used to not break AuthenticationChecker and ContextListener when
 * using the authenticator system. Once the authenticator system is no longer
 * experimental, this class can be used to trigger deprecation notices.
 *
 * @internal
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class NoopAuthenticationManager implements AuthenticationManagerInterface
{
    public function authenticate(TokenInterface $token)
    {
        return $token;
    }
}
