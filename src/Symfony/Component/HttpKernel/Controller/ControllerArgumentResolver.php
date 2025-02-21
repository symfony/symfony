<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\ArgumentResolver;
use Symfony\Component\ArgumentResolver\SourceValue;
use Symfony\Component\ArgumentResolver\ValueResolver\DefaultValueResolver;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ControllerValueResolverInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\SessionValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\VariadicValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata as LegacyArgumentMetadata;

/**
 * Responsible for resolving the arguments passed to an action.
 */
final class ControllerArgumentResolver extends ArgumentResolver
{
    /**
     * @param Request $input
     */
    public function getArguments(mixed $input, callable $callable, ?\ReflectionFunctionAbstract $reflector = null): array
    {
        if (!$input instanceof Request) {
            throw new \InvalidArgumentException(sprintf('The "$request" argument must be an instance of %s, got "%s".', Request::class, get_debug_type($request)));
        }

        return parent::getArguments($input, $callable, $reflector);
    }

    public static function getDefaultValueResolvers(): iterable
    {
        return [
            new RequestAttributeValueResolver(),
            new RequestValueResolver(),
            new SessionValueResolver(),
            new DefaultValueResolver(),
            new VariadicValueResolver(),
        ];
    }

    protected static function getExtraValueResolversForNamed(): array
    {
        return [
            new RequestAttributeValueResolver(),
            new DefaultValueResolver(),
        ];
    }

    /**
     * @param ControllerValueResolverInterface|LegacyValueResolverInterface $resolver
     * @param Request $input
     */
    protected function callResolver($resolver, ArgumentMetadata $metadata, mixed $input): iterable
    {
        if ($resolver instanceof ValueResolverInterface) {
            return $resolver->resolveArgument($metadata, $resolver instanceof ControllerValueResolverInterface ? $resolver->extractSourceValue($metadata, $input) : new SourceValue(null));
        } else {
            trigger_deprecation('symfony/http-kernel', '7.3', \sprintf('The "%s" interface is deprecated, implement "%s" in "%s" instead.', LegacyValueResolverInterface::class, ControllerValueResolverInterface::class, $resolver::class));

            return $resolver->resolve($input, LegacyArgumentMetadata::fromBaseArgumentMetadata($metadata));
        }
    }
}
