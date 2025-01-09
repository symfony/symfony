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

use Symfony\Component\ArgumentResolver\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;

/**
 * Yields the Session.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class SessionValueResolver implements ControllerValueResolverInterface
{
    public function resolve(ArgumentMetadata $argument, ?Request $request = null): iterable
    {
        if (!$request) {
            throw new InvalidArgumentException(sprintf('The "$request" argument must be a "%s" instance, null given.', Request::class));
        }

        if (!$request->hasSession()) {
            return [];
        }

        $type = $argument->getType();
        if (SessionInterface::class !== $type && !is_subclass_of($type, SessionInterface::class)) {
            return [];
        }

        return $request->getSession() instanceof $type ? [$request->getSession()] : [];
    }
}
