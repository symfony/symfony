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

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

/**
 * @experimental
 */
final class UidClassSchemaResolver implements ClassSchemaResolverInterface
{
    public function resolve(string $class, Configuration $config): ?array
    {
        return match (true) {
            is_a($class, Uuid::class, true) => ['type' => 'string', 'format' => 'uuid'],
            is_a($class, Ulid::class, true) => ['type' => 'string', 'format' => 'ulid'],
            default => null,
        };
    }
}
