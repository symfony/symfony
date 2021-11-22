<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\AbstractUid;

final class UidValueResolver implements ArgumentValueResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return !$argument->isVariadic()
            && \is_string($request->attributes->get($argument->getName()))
            && null !== $argument->getType()
            && is_subclass_of($argument->getType(), AbstractUid::class, true);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        /** @var class-string<AbstractUid> $uidClass */
        $uidClass = $argument->getType();

        try {
            return [$uidClass::fromString($request->attributes->get($argument->getName()))];
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException(sprintf('The uid for the "%s" parameter is invalid.', $argument->getName()), $e);
        }
    }
}
