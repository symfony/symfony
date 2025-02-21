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

use Psr\Container\ContainerInterface;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Exception\InvalidSourceValueException;
use Symfony\Component\ArgumentResolver\SourceValue;
use Symfony\Component\ArgumentResolver\ValueResolver\NotTaggedCallableValueResolver;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata as LegacyArgumentMetadata;

/**
 * Provides an intuitive error message when controller fails because it is not registered as a service.
 *
 * @author Simeon Kolev <simeon.kolev9@gmail.com>
 */
final class NotTaggedControllerValueResolver implements ControllerValueResolverInterface, LegacyValueResolverInterface
{
    private ValueResolverInterface $inner;

    public function __construct(
        ValueResolverInterface|ContainerInterface $inner,
    ) {
        if ($inner instanceof ContainerInterface) {
            trigger_deprecation('symfony/http-kernel', '7.3', sprintf('The "$container" argument of "%s::__construct()" is deprecated, pass a "%s" instance as "$inner" instead.', __CLASS__, NotTaggedCallableValueResolver::class));
            $this->inner = new NotTaggedCallableValueResolver($inner);
            return;
        }
        $this->inner = new NotTaggedCallableValueResolver($inner);
    }

    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        try {
            return $this->inner->resolveArgument($argument, $value);
        } catch (RuntimeException $e) {
            throw new RuntimeException(str_replace('?', ' or missed tagging it with the "controller.service_arguments"?', $e->getMessage()));
        }
    }

    public function extractSourceValue(ArgumentMetadata $argument, Request $request): SourceValue
    {
        return new SourceValue($request->attributes->get('_controller'));

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
