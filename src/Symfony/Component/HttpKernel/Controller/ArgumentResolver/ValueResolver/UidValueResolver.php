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
use Symfony\Component\ArgumentResolver\Exception\InvalidRawValueException;
use Symfony\Component\ArgumentResolver\ValueResolver\Traits\UidValueResolverTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UidValueResolver implements ControllerValueResolverInterface
{
    use UidValueResolverTrait;

    public function resolve(ArgumentMetadata $argument, ?Request $request = null): iterable
    {
        if (!$request) {
            throw new InvalidArgumentException(sprintf('The "$request" argument must be a "%s" instance, null given.', Request::class));
        }

        if (!$request->attributes->has($argument->getName())) {
            return [];
        }

        try {
            return $this->doResolve($argument, [$request->attributes->get($argument->getName())]);
        } catch (InvalidRawValueException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e->getPrevious());
        }
    }
}
