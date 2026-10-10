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
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionPolicyInterface;

class ConstantNameDefinitionPolicy implements DefinitionPolicyInterface
{
    public function __construct(private readonly string $name)
    {
    }

    public function nameFor(string $class, Configuration $config, ?DefinitionParent $parent = null): string
    {
        return $this->name;
    }
}
