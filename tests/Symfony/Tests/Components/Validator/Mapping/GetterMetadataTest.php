<?php

namespace Symfony\Tests\Components\Validator\Mapping;

require_once __DIR__.'/../Fixtures/Entity.php';

use Symfony\Components\Validator\Mapping\GetterMetadata;
use Symfony\Tests\Components\Validator\Fixtures\Entity;

class GetterMetadataTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Tests\Components\Validator\Fixtures\Entity';

    public function testInvalidPropertyName()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\ValidatorException');

        new GetterMetadata(self::CLASSNAME, 'foobar');
    }

    public function testGetValueFromPublicGetter()
    {
        // private getters don't work yet because ReflectionMethod::setAccessible()
        // does not exists yet in a stable PHP release

        $entity = new Entity('foobar');
        $metadata = new GetterMetadata(self::CLASSNAME, 'internal');

        $this->assertEquals('foobar from getter', $metadata->getValue($entity));
    }
}

