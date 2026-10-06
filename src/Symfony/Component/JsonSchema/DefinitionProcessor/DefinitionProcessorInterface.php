<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\DefinitionProcessor;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;

/**
 * Post-processes every object definition once its properties are assembled.
 *
 * @experimental
 */
interface DefinitionProcessorInterface
{
    /**
     * @param array<string, mixed> $definition
     * @param class-string         $class
     * @param ?DefinitionParent    $parent     null for the root definition
     *
     * @return array<string, mixed>
     */
    public function process(array $definition, string $class, Configuration $config, ?DefinitionParent $parent): array;
}
