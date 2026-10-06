<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\ClassSchemaResolver;

use BcMath\Number;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;

/**
 * @experimental
 */
final class NativeClassSchemaResolver implements ClassSchemaResolverInterface
{
    public function resolve(string $class, Configuration $config, ?DefinitionParent $parent = null): ?array
    {
        return match (true) {
            is_a($class, \DateTimeInterface::class, true) => ['type' => 'string', 'format' => 'date-time'],
            is_a($class, \DateInterval::class, true) => ['type' => 'string', 'format' => 'duration'],
            is_a($class, \SplFileInfo::class, true) => ['type' => 'string', 'format' => 'binary'],
            is_a($class, Number::class, true) => ['type' => 'string'],
            is_a($class, \BackedEnum::class, true) => [
                'type' => 'int' === (string) (new \ReflectionEnum($class))->getBackingType() ? 'integer' : 'string',
                'enum' => array_map(static fn (\BackedEnum $case): int|string => $case->value, $class::cases()),
            ],
            is_a($class, \UnitEnum::class, true) => [
                'type' => 'string',
                'enum' => array_map(static fn (\UnitEnum $case): string => $case->name, $class::cases()),
            ],
            default => null,
        };
    }
}
