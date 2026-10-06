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
 * @experimental
 */
interface SchemaGeneratorInterface
{
    public function generate(Type $type, Configuration $config = new Configuration()): Schema;
}
