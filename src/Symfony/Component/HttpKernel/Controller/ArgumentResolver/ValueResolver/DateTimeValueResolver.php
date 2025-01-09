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
use Symfony\Component\ArgumentResolver\ValueResolver\Traits\DateTimeValueResolverTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Convert DateTime instances from request attribute variable.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Tim Goudriaan <tim@codedmonkey.com>
 */
final class DateTimeValueResolver implements ControllerValueResolverInterface
{
    use DateTimeValueResolverTrait;

    public function resolve(ArgumentMetadata $argument, ?Request $request = null): iterable
    {
        if (!$request) {
            throw new InvalidArgumentException(sprintf('The "$request" argument must be a "%s" instance, null given.', Request::class));
        }

        if (!$request->attributes->has($argument->getName())) {
            return [];
        }

        return $this->doResolve($argument, [$request->attributes->get($argument->getName())]);
    }
}
