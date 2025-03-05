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
use Symfony\Component\ArgumentResolver\ArgumentValueSource\SourceValue;
use Symfony\Component\ArgumentResolver\ValueResolver\ServiceValueResolver as BaseServiceValueResolver;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata as LegacyArgumentMetadata;

/**
 * Yields a service keyed by _controller and argument name.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class ServiceValueResolver implements ControllerValueResolverInterface, LegacyValueResolverInterface
{
    private ValueResolverInterface $inner;

    public function __construct(
        ValueResolverInterface|ContainerInterface $inner,
    ) {
        if ($inner instanceof ContainerInterface) {
            trigger_deprecation('symfony/http-kernel', '7.3', sprintf('The "$container" argument of "%s::__construct()" is deprecated, pass a "%s" instance as "$inner" instead.', __CLASS__, BaseServiceValueResolver::class));
            $this->inner = new BaseServiceValueResolver($inner);
            return;
        }
        $this->inner = $inner;
    }

    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        return $this->inner->resolveArgument($argument, $value);
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
