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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CustomAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    /**
     * @param array $options Options for processing a successful authentication attempt
     */
    public function __construct(
        private AuthenticationFailureHandlerInterface $handler,
        array $options,
    ) {
        if (method_exists($handler, 'setOptions')) {
            $this->handler->setOptions($options);
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->handler->onAuthenticationFailure($request, $exception);
    }
}
