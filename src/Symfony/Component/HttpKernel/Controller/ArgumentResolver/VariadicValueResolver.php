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

use Doctrine\Common\Collections\Expr\Value;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\SourceValue;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\ArgumentResolver\ValueResolver\VariadicValueResolver as BaseVariadicValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata as LegacyArgumentMetadata;

/**
 * Yields a variadic argument's values from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class VariadicValueResolver implements ControllerValueResolverInterface, LegacyValueResolverInterface
{
    public function __construct(private readonly ValueResolverInterface $inner = new BaseVariadicValueResolver())
    {
    }

    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        return $this->inner->resolveArgument($argument, $value);
    }

    public function extractSourceValue(ArgumentMetadata $argument, Request $request): SourceValue
    {
        return $request->attributes->has($argument->getName())
            ? new SourceValue($request->attributes->get($argument->getName()))
            : SourceValue::notFound();
    }

    /**
     * @deprecated since Symfony 7.3, use resolveArgument() instead
     */
    public function resolve(Request $request, LegacyArgumentMetadata $argument): iterable
    {
        trigger_deprecation('symfony/http-kernel', '7.3', \sprintf('The "%s()" method is deprecated, use "resolveArgument()" instead.', __METHOD__));

        return $this->resolveArgument($argument, $this->extractSourceValue($argument, $request));
    }
}
