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

use Psr\Clock\ClockInterface;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Exception\InvalidSourceValueException;
use Symfony\Component\ArgumentResolver\SourceValue;
use Symfony\Component\ArgumentResolver\ValueResolver\DateTimeValueResolver as BaseDateTimeValueResolver;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as LegacyValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata as LegacyArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Convert DateTime instances from request attribute variable.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Tim Goudriaan <tim@codedmonkey.com>
 */
final class DateTimeValueResolver implements ControllerValueResolverInterface, LegacyValueResolverInterface
{
    private ValueResolverInterface $inner;

    public function __construct(
        ClockInterface|BaseDateTimeValueResolver|null $inner = null,
    ) {
        if ($inner instanceof ClockInterface) {
            trigger_deprecation('symfony/http-kernel', '7.3', sprintf('Passing a "%s" instance to "%s::__construct() is deprecated, pass a "%s" instance as "$inner" instead.', __CLASS__, ClockInterface::class, BaseDateTimeValueResolver::class));
            $this->inner = new BaseDateTimeValueResolver($inner);
            return;
        }

        $this->inner = $inner ?? new BaseDateTimeValueResolver();
    }

    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        try {
            return $this->inner->resolveArgument($argument, $value);
        } catch(InvalidSourceValueException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }

    public function extractSourceValue(ArgumentMetadata $argument, Request $request): SourceValue
    {
        if (!$request->attributes->has($argument->getName())) {
            return new SourceValue([]);
        }

        return new SourceValue($request->attributes->get($argument->getName()));
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
