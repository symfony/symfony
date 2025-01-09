<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver;

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Exception\InvalidArgumentException;
use Symfony\Component\ArgumentResolver\ValueResolver\Traits\ServiceValueResolverTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Yields a service keyed by _controller and argument name.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ServiceValueResolver implements ControllerValueResolverInterface
{
    use ServiceValueResolverTrait;

    public function resolve(ArgumentMetadata $argument, ?Request $request = null): iterable
    {
        if (!$request) {
            throw new InvalidArgumentException(sprintf('The "$request" argument must be a "%s" instance, null given.', Request::class));
        }

        $controller = $request->attributes->get('_controller');

        if (\is_array($controller) && \is_callable($controller, true) && \is_string($controller[0])) {
            $controller = $controller[0].'::'.$controller[1];
        } elseif (!\is_string($controller) || '' === $controller) {
            return [];
        }

        return $this->doResolve($argument, [$controller]);
    }
}
