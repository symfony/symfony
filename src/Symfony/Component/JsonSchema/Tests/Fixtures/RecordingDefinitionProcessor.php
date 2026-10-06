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
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\SchemaDocument;

class RecordingDefinitionProcessor implements DefinitionProcessorInterface
{
    /**
     * @var list<array{string, ?DefinitionParent}>
     */
    public array $calls = [];

    public function __construct(
        private readonly string $marker = 'recorded',
    ) {
    }

    public function process(array $definition, string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent): array
    {
        $this->calls[] = [$class, $parent];
        $definition['x-processors'][] = $this->marker;

        return $definition;
    }
}
