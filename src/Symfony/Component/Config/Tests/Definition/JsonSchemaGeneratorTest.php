<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\JsonSchemaGenerator;
use Symfony\Component\Config\Tests\Fixtures\Configuration\ExampleConfiguration;

class JsonSchemaGeneratorTest extends TestCase
{
    private string $schemaFile;

    public function testExampleConfiguration()
    {
        $this->schemaFile = tempnam(sys_get_temp_dir(), 'json-schema-generator');
        $configuration = new ExampleConfiguration();

        $root = new ArrayNode(null);
        $root->addChild($configuration->getConfigTreeBuilder()->buildTree());

        $generator = new JsonSchemaGenerator($this->schemaFile);
        $generator->build($root);

        // Uncomment to update the schema file
        // file_put_contents(__DIR__.'/../Fixtures/Configuration/ExampleConfiguration.schema.json', file_get_contents($this->schemaFile));

        self::assertJsonFileEqualsJsonFile(__DIR__.'/../Fixtures/Configuration/ExampleConfiguration.schema.json', $this->schemaFile);
    }

    #[After]
    public function removeTempFiles()
    {
        unlink($this->schemaFile);
    }
}
