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

use Symfony\Component\Validator\Mapping\GetterMetadata;
use Symfony\Component\Validator\Tests\Fixtures\Entity;

class GetterMetadataTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Component\Validator\Tests\Fixtures\Entity';

    public function testInvalidPropertyName()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ValidatorException');

        new GetterMetadata(self::CLASSNAME, 'foobar');
    }

    public function testGetPropertyValueFromPublicGetter()
    {
        // private getters don't work yet because ReflectionMethod::setAccessible()
        // does not exists yet in a stable PHP release

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
}
