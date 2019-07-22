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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CustomAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private $handler;

    /**
     * @param AuthenticationSuccessHandlerInterface $handler     An AuthenticationSuccessHandlerInterface instance
     * @param array                                 $options     Options for processing a successful authentication attempt
     * @param string                                $providerKey The provider key
     */
    public function __construct(AuthenticationSuccessHandlerInterface $handler, array $options, string $providerKey)
    {
        $this->handler = $handler;
        if (method_exists($handler, 'setOptions')) {
            $this->handler->setOptions($options);
        }
        if (method_exists($handler, 'setProviderKey')) {
            $this->handler->setProviderKey($providerKey);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        return $this->handler->onAuthenticationSuccess($request, $token);
    }
}
