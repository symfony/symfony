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

use Symfony\Component\JsonSchema\ClassSchemaResolver\ClassSchemaResolverInterface;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;

class RecordingClassSchemaResolver implements ClassSchemaResolverInterface
{
    /**
     * @var list<array{string, ?DefinitionParent}>
     */
    public array $calls = [];

    public function resolve(string $class, Configuration $config, ?DefinitionParent $parent = null): ?array
    {
        $this->calls[] = [$class, $parent];

        return null;
    }
}
