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
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;

class RecordingDefinitionPolicy implements DefinitionPolicyInterface
{
    /**
     * @var list<array{string, ?DefinitionParent}>
     */
    public array $calls = [];

    private readonly ShortNameDefinitionPolicy $decorated;

    public function __construct()
    {
        $this->decorated = new ShortNameDefinitionPolicy();
    }

    public function nameFor(string $class, Configuration $config, ?DefinitionParent $parent = null): string
    {
        $this->calls[] = [$class, $parent];

        return $this->decorated->nameFor($class, $config, $parent);
    }
}
