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
use Symphony\Component\Validator\Mapping\GetterMetadata;
use Symphony\Component\Validator\Tests\Fixtures\Entity;

class GetterMetadataTest extends TestCase
{
    const CLASSNAME = 'Symphony\Component\Validator\Tests\Fixtures\Entity';

    public function testInvalidPropertyName()
    {
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symphony\Component\Validator\Exception\ValidatorException');

        new GetterMetadata(self::CLASSNAME, 'foobar');
    }

    public function testGetPropertyValueFromPublicGetter()
    {
        // private getters don't work yet because ReflectionMethod::setAccessible()
        // does not exist yet in a stable PHP release

        $entity = new Entity('foobar');
        $metadata = new GetterMetadata(self::CLASSNAME, 'internal');

        $this->assertEquals('foobar from getter', $metadata->getPropertyValue($entity));
    }

    public function testGetPropertyValueFromOverriddenPublicGetter()
    {
        $entity = new Entity();
        $metadata = new GetterMetadata(self::CLASSNAME, 'data');

        $this->assertEquals('Overridden data', $metadata->getPropertyValue($entity));
    }

    public function testGetPropertyValueFromIsser()
    {
        $entity = new Entity();
        $metadata = new GetterMetadata(self::CLASSNAME, 'valid', 'isValid');

        $this->assertEquals('valid', $metadata->getPropertyValue($entity));
    }

    public function testGetPropertyValueFromHasser()
    {
        $entity = new Entity();
        $metadata = new GetterMetadata(self::CLASSNAME, 'permissions');

        $this->assertEquals('permissions', $metadata->getPropertyValue($entity));
    }

    /**
     * @expectedException \Symphony\Component\Validator\Exception\ValidatorException
     * @expectedExceptionMessage The hasLastName() method does not exist in class Symphony\Component\Validator\Tests\Fixtures\Entity.
     */
    public function testUndefinedMethodNameThrowsException()
    {
        new GetterMetadata(self::CLASSNAME, 'lastName', 'hasLastName');
    }
}
