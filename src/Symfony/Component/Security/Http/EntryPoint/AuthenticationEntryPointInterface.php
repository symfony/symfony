<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EntryPoint;

use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\HttpFoundation\Request;

/**
 * AuthenticationEntryPointInterface is the interface used to start the
 * authentication scheme.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface AuthenticationEntryPointInterface
{
    /**
     * Starts the authentication scheme.
     *
     * @param EventInterface          $event         The "core.security" event
     * @param object                  $request       The request that resulted in an AuthenticationException
     * @param AuthenticationException $authException The exception that started the authentication process
     */
    function start(EventInterface $event, Request $request, AuthenticationException $authException = null);
}
