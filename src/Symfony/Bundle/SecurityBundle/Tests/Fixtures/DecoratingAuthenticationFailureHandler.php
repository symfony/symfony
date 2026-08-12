<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class DecoratingAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(private AuthenticationFailureHandlerInterface $handler)
    {
    }

    public function setOptions(array $options): void
    {
        if (method_exists($this->handler, 'setOptions')) {
            $this->handler->setOptions($options);
        }
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $response = $this->handler->onAuthenticationFailure($request, $exception);
        $response->headers->add([
            'X-Decorated-Handler' => $this->handler::class,
            'X-Decorating-Handler' => __CLASS__,
        ]);

        return $response;
    }
}
