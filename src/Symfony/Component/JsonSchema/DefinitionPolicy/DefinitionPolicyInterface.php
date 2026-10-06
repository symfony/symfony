<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\DefinitionPolicy;

use Symfony\Component\JsonSchema\Configuration;

/**
 * Names the definition describing a class under a given configuration.
 *
 * @experimental
 */
interface DefinitionPolicyInterface
{
    /**
     * @param class-string      $class
     * @param ?DefinitionParent $parent null for the root definition
     */
    public function nameFor(string $class, Configuration $config, ?DefinitionParent $parent = null): string;
}
