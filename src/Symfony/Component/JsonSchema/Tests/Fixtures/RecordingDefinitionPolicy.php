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
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;

class RecordingDefinitionPolicy implements DefinitionPolicyInterface
{
    /**
     * @var list<array{ObjectType|GenericType<ObjectType>, ?DefinitionParent}>
     */
    public array $calls = [];

    private readonly ShortNameDefinitionPolicy $decorated;

    public function __construct()
    {
        $this->decorated = new ShortNameDefinitionPolicy();
    }

    public function nameFor(ObjectType|GenericType $type, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): string
    {
        $this->calls[] = [$type, $parent];

        return $this->decorated->nameFor($type, $config, $document, $parent);
    }
}
