<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionPolicyInterface;
use Symfony\Component\JsonSchema\SchemaDocument;

class ConstantNameDefinitionPolicy implements DefinitionPolicyInterface
{
    public function __construct(private readonly string $name)
    {
    }

    public function nameFor(string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): string
    {
        return $this->name;
    }
}
