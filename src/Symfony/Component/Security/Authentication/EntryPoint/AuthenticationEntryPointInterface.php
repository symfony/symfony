<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Authentication\EntryPoint;

use Symfony\Component\Security\Exception\AuthenticationException;
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
     * @param object                  $request       The request that resulted in an AuthenticationException
     * @param AuthenticationException $authException The exception that started the authentication process
     */
    function start(Request $request, AuthenticationException $authException = null);
}
