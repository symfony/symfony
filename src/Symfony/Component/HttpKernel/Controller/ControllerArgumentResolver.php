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
use Symfony\Component\ArgumentResolver\ValueResolver\DefaultValueResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata as LegacyArgumentMetadata;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\ControllerValueResolverInterface;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\RequestValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\SessionValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\VariadicValueResolver;
use Symfony\Component\ArgumentResolver\ArgumentResolver;

/**
 * Responsible for resolving the arguments passed to an action.
 */
final class ControllerArgumentResolver extends ArgumentResolver
{
    /**
     * @param Request $request
     */
    public function getArguments(mixed $request, callable $callable, ?\ReflectionFunctionAbstract $reflector = null): array
    {
        if (!$request instanceof Request) {
            throw new \InvalidArgumentException(sprintf('The "$request" argument must be an instance of %s, got "%s".', Request::class, get_debug_type($request)));
        }

        return parent::getArguments($request, $callable, $reflector);
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
        if ($resolver instanceof LegacyValueResolverInterface) {
            trigger_deprecation('symfony/http-kernel', '7.3', sprintf('The "%s" interface is deprecated, implement "%s" instead.', LegacyValueResolverInterface::class, ControllerValueResolverInterface::class));
            return $resolver->resolve($input, LegacyArgumentMetadata::fromBaseArgumentMetadata($metadata));
        } else {
            return $resolver->resolve($metadata, $input);
        }
    }
}
