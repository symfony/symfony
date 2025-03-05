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

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\ArgumentValueSource\SourceValue;
use Symfony\Component\ArgumentResolver\Exception\NearMissValueResolverException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata as LegacyArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NearMissValueResolverException as LegacyNearMissValueResolverException;

/**
 * Yields the same instance as the request object passed along.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class RequestValueResolver implements ControllerValueResolverInterface, LegacyValueResolverInterface
{
    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        if (Request::class === $argument->getType() || is_subclass_of($argument->getType(), Request::class)) {
            return [$value->get()];
        }

        if (str_ends_with($argument->getType() ?? '', '\\Request')) {
            throw new NearMissValueResolverException(\sprintf('Looks like you required a Request object with the wrong class name "%s". Did you mean to use "%s" instead?', $argument->getType(), Request::class));
        }

        return [];
    }

    public function extractSourceValue(ArgumentMetadata $argument, Request $request): SourceValue
    {
        return new SourceValue($request);
    }

    /**
     * @deprecated since Symfony 7.3, use resolveArgument() instead
     */
    public function resolve(Request $request, LegacyArgumentMetadata $argument): iterable
    {
        trigger_deprecation('symfony/http-kernel', '7.3', \sprintf('The "%s()" method is deprecated, use "resolveArgument()" instead.', __METHOD__));

        try {
            return $this->resolveArgument($argument, $this->extractSourceValue($argument, $request));
        } catch (NearMissValueResolverException $e) {
            throw new LegacyNearMissValueResolverException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
