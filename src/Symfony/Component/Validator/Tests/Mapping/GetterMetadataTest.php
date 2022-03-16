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
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Mapping\GetterMetadata;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;

class GetterMetadataTest extends TestCase
{
    private const CLASSNAME = Entity::class;

    public function testInvalidPropertyName()
    {
        $this->expectException(ValidatorException::class);

        new GetterMetadata(self::CLASSNAME, 'foobar');
    }

    public function testGetPropertyValueFromPublicGetter()
    {
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

    public function testUndefinedMethodNameThrowsException()
    {
        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage('The "hasLastName()" method does not exist in class "Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity".');
        new GetterMetadata(self::CLASSNAME, 'lastName', 'hasLastName');
    }
}
