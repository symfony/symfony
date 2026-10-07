<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema;

use Symfony\Component\TypeInfo\Type;

/**
 * Generates the JSON Schema of a type.
 *
 * Objects are described by definitions referenced from the root (see {@see Configuration::$references}),
 * the returned {@see Schema} holds both and renders them for the configured dialect.
 *
 * @experimental
 */
interface SchemaGeneratorInterface
{
    public function generate(Type $type, Configuration $config = new Configuration()): Schema;
}
