<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\Mapping\PropertyMetadata;
use Symphony\Component\Validator\Tests\Fixtures\Entity;

class PropertyMetadataTest extends TestCase
{
    const CLASSNAME = 'Symphony\Component\Validator\Tests\Fixtures\Entity';
    const PARENTCLASS = 'Symphony\Component\Validator\Tests\Fixtures\EntityParent';

    public function testInvalidPropertyName()
    {
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symphony\Component\Validator\Exception\ValidatorException');

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

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symphony\Component\Validator\Exception\ValidatorException');
        $metadata->getPropertyValue($entity);
    }
}
