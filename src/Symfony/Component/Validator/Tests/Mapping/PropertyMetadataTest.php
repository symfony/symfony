<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\Entity_74;

class PropertyMetadataTest extends TestCase
{
    const CLASSNAME = 'Symfony\Component\Validator\Tests\Fixtures\Entity';
    const CLASSNAME_74 = 'Symfony\Component\Validator\Tests\Fixtures\Entity_74';
    const PARENTCLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityParent';

    public function testInvalidPropertyName()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ValidatorException');

        new PropertyMetadata(self::CLASSNAME, 'foobar');
    }

    public function testGetPropertyValueFromPrivateProperty()
    {
        $entity = new Entity('foobar');
        $metadata = new PropertyMetadata(self::CLASSNAME, 'internal');

        $this->assertEquals('foobar', $metadata->getPropertyValue($entity));
    }

    public function testGetPropertyValueFromOverriddenPrivateProperty()
    {
        $entity = new Entity('foobar');
        $metadata = new PropertyMetadata(self::PARENTCLASS, 'data');

        $this->assertTrue($metadata->isPublic($entity));
        $this->assertEquals('Overridden data', $metadata->getPropertyValue($entity));
    }

    public function testGetPropertyValueFromRemovedProperty()
    {
        $entity = new Entity('foobar');
        $metadata = new PropertyMetadata(self::CLASSNAME, 'internal');
        $metadata->name = 'test';

        $this->expectException('Symfony\Component\Validator\Exception\ValidatorException');
        $metadata->getPropertyValue($entity);
    }

    /**
     * @requires PHP 7.4
     */
    public function testGetPropertyValueFromUninitializedProperty()
    {
        $entity = new Entity_74();
        $metadata = new PropertyMetadata(self::CLASSNAME_74, 'uninitialized');

        $this->assertNull($metadata->getPropertyValue($entity));
    }
}
