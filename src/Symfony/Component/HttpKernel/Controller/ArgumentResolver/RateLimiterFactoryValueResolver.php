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
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\RateLimiter\Attribute\PolicyInterface;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Resolves the RateLimiterFactoryInterface arguments of a controller that carry a policy attribute,
 * such as #[SlidingWindowPolicy], to a factory of limiters that only this argument uses.
 *
 * Several policies on the same argument are combined into a compound limiter.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RateLimiterFactoryValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly RateLimiterBuilder $builder,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (RateLimiterFactoryInterface::class !== $argument->getType() || !$policies = $argument->getAttributesOfType(PolicyInterface::class, ArgumentMetadata::IS_INSTANCEOF)) {
            return [];
        }

        $id = $argument->getControllerName().'$'.$argument->getName();

        if (1 === \count($policies)) {
            return [$policies[0]->createFactory($this->builder, $id)];
        }

        $factories = [];
        foreach ($policies as $i => $policy) {
            $factories[] = $policy->createFactory($this->builder, $id.'#'.$i);
        }

        return [$this->builder->compound(...$factories)];
    }
}
