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
use Symfony\Component\HttpKernel\Attribute\MapDateTime;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Convert DateTime instances from request attribute variable.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Tim Goudriaan <tim@codedmonkey.com>
 */
final class DateTimeValueResolver implements ArgumentValueResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return is_a($argument->getType(), \DateTimeInterface::class, true) && $request->attributes->has($argument->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $value = $request->attributes->get($argument->getName());

        if ($argument->isNullable() && !$value) {
            yield null;

            return;
        }

        $class = \DateTimeInterface::class === $argument->getType() ? \DateTimeImmutable::class : $argument->getType();
        $format = null;

        if ($attributes = $argument->getAttributes(MapDateTime::class, ArgumentMetadata::IS_INSTANCEOF)) {
            $attribute = $attributes[0];
            $format = $attribute->format;
        }

        $date = false;

        if (null !== $format) {
            $date = $class::createFromFormat($format, $value);

            if ($class::getLastErrors()['warning_count']) {
                $date = false;
            }
        } else {
            if (false !== filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
                $value = '@'.$value;
            }
            try {
                $date = new $class($value);
            } catch (\Exception) {
                $date = false;
            }
        }

        if (!$date) {
            throw new NotFoundHttpException(sprintf('Invalid date given for parameter "%s".', $argument->getName()));
        }

        yield $date;
    }
}
