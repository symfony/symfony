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
use Symfony\Component\ArgumentResolver\Exception\InvalidSourceValueException;
use Symfony\Component\ArgumentResolver\ValueResolver\BackedEnumValueResolver as BaseBackedEnumValueResolver;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata as LegacyArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Attempt to resolve backed enum cases from request attributes, for a route path parameter,
 * leading to a 404 Not Found if the attribute value isn't a valid backing value for the enum type.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class BackedEnumValueResolver implements ControllerValueResolverInterface, LegacyValueResolverInterface
{
    public function __construct(private ValueResolverInterface $inner = new BaseBackedEnumValueResolver())
    {
        if (!$inner instanceof ValueResolverInterface) {
            trigger_deprecation('symfony/http-kernel', '7.3', \sprintf('Not passing an instance of "%" as $inner is deprecated.', ValueResolverInterface::class));
            $this->inner = new BaseBackedEnumValueResolver();
        }
    }

    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        try {
            return $this->inner->resolveArgument($argument, $value);
        } catch (InvalidSourceValueException $e) {
            throw new NotFoundHttpException(\sprintf('Could not resolve the "%s $%s" controller argument: ', $argument->getType(), $argument->getName()).$e->getPrevious()->getMessage(), $e);
        }
    }

    public function extractSourceValue(ArgumentMetadata $argument, Request $request): SourceValue
    {
        // do not support if no value can be resolved at all
        // letting the \Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver be used
        // or \Symfony\Component\HttpKernel\Controller\ArgumentResolver fail with a meaningful error.
        if (!$request->attributes->has($argument->getName())) {
            return SourceValue::notFound();
        }

        return new SourceValue($request->attributes->get($argument->getName()));
    }

    /**
     * @deprecated since Symfony 7.3, use `resolveArgument()` instead
     */
    public function resolve(Request $request, LegacyArgumentMetadata $argument): iterable
    {
        // trigger_deprecation
        return $this->resolveArgument($argument, $this->extractSourceValue($argument, $request));
    }
}
