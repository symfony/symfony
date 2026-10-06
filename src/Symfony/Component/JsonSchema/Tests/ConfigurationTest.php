<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\Direction;
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\JsonSchema\ReferenceStrategy;

class ConfigurationTest extends TestCase
{
    public function testDefaults()
    {
        $config = new Configuration();

        $this->assertEquals(Dialect::jsonSchema202012(), $config->dialect);
        $this->assertSame(Direction::Response, $config->direction);
        $this->assertSame(ReferenceStrategy::ByDefinition, $config->references);
        $this->assertSame([], $config->groups);
        $this->assertNull($config->attributes);
        $this->assertSame([], $config->ignoredAttributes);
        $this->assertTrue($config->allowExtraAttributes);
        $this->assertFalse($config->partial);
        $this->assertNull($config->validationGroups);
        $this->assertNull($config->definitionName);
        $this->assertNull($config->definitionPrefix);
        $this->assertNull($config->format);
    }

    public function testWithChangesOnlyTheGivenValues()
    {
        $config = new Configuration(dialect: Dialect::openApi30(), groups: ['read'], definitionPrefix: 'Book');

        $changed = $config->with(partial: true, definitionPrefix: null);

        $this->assertNotSame($config, $changed);
        $this->assertEquals(Dialect::openApi30(), $changed->dialect);
        $this->assertSame(['read'], $changed->groups);
        $this->assertTrue($changed->partial);
        $this->assertNull($changed->definitionPrefix);
        $this->assertSame('Book', $config->definitionPrefix);
    }

    public function testWithRejectsUnknownValues()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"description"');

        (new Configuration())->with(description: 'nope');
    }
}
