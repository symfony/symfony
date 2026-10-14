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

class ConfigurationTest extends TestCase
{
    public function testDefaults()
    {
        $config = new Configuration();

        $this->assertEquals(Dialect::jsonSchema202012(), $config->dialect);
        $this->assertSame([], $config->groups);
        $this->assertNull($config->attributes);
        $this->assertSame([], $config->ignoredAttributes);
        $this->assertTrue($config->allowExtraAttributes);
        $this->assertNull($config->validationGroups);
        $this->assertNull($config->definitionName);
        $this->assertNull($config->definitionPrefix);
        $this->assertNull($config->format);
        $this->assertNull($config->direction);
        $this->assertSame([], $config->context);
    }

    public function testGroupsAndIgnoredAttributesAreSorted()
    {
        $config = new Configuration(groups: ['write', 'read', 'write'], ignoredAttributes: ['name', 'id']);

        $this->assertSame(['read', 'write'], $config->groups);
        $this->assertSame(['id', 'name'], $config->ignoredAttributes);
    }

    public function testClosureValidationGroupsAreRejected()
    {
        $this->expectException(\TypeError::class);

        new Configuration(validationGroups: static fn (): array => ['create']);
    }
}
