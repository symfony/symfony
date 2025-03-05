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

trigger_deprecation('symfony/http-kernel', '7.3', 'The "%s" class is deprecated, use "%s" instead.', DefaultValueResolver::class, BaseDefaultValueResolver::class);

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\ArgumentValueSource\SourceValue;
use Symfony\Component\ArgumentResolver\ValueResolver\DefaultValueResolver as BaseDefaultValueResolver;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata as LegacyArgumentMetadata;

/**
 * Yields the default value defined in the action signature when no value has been given.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 *
 * @deprecated since Symfony 7.3, use {@see BaseDefaultValueResolver} instead
 */
final class DefaultValueResolver implements LegacyValueResolverInterface, ControllerValueResolverInterface
{
    private ValueResolverInterface $inner;

    public function __construct()
    {
        $this->inner = new BaseDefaultValueResolver();
    }

    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        return $this->inner->resolveArgument($argument, $value);
    }

    public function extractSourceValue(ArgumentMetadata $argument, Request $request): SourceValue
    {
        return new SourceValue(null);
    }

    public function resolve(Request $request, LegacyArgumentMetadata $argument): iterable
    {
        return $this->resolveArgument($argument, new SourceValue(null));
    }
}
