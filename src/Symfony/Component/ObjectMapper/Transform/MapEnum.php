<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Transform;

use Symfony\Component\ObjectMapper\Exception\MappingTransformException;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * @internal
 *
 * @implements TransformCallableInterface<object, object>
 *
 * @author Julien Robic <nayte91@gmail.com>
 */
final class MapEnum implements TransformCallableInterface
{
    /** @param string|class-string<\BackedEnum> $targetType */
    public function __construct(
        private readonly string $targetType,
    ) {
    }

    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        try {
            return $this->targetType::from($value);
        } catch (\ValueError $e) {
            throw new MappingTransformException(\sprintf('Invalid value "%s" for enum "%s": "%s".', $value, $this->targetType, $e->getMessage()), 0, $e);
        }
    }
}
