<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Authentication;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 */
class CustomAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    private $handler;

    /**
     * @param AuthenticationFailureHandlerInterface $handler An AuthenticationFailureHandlerInterface instance
     * @param array                                 $options Options for processing a successful authentication attempt
     */
    public function __construct(AuthenticationFailureHandlerInterface $handler, array $options)
    {
        $this->handler = $handler;
        if (method_exists($handler, 'setOptions')) {
            $this->handler->setOptions($options);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        return $this->handler->onAuthenticationFailure($request, $exception);
    }
}
