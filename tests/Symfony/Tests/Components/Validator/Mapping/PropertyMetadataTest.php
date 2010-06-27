<?php

namespace Symfony\Tests\Components\Validator\Mapping;

require_once __DIR__.'/../Fixtures/Entity.php';

use Symfony\Components\Validator\Mapping\PropertyMetadata;
use Symfony\Tests\Components\Validator\Fixtures\Entity;

class PropertyMetadataTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Tests\Components\Validator\Fixtures\Entity';

    public function testInvalidPropertyName()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\ValidatorException');

        new PropertyMetadata(self::CLASSNAME, 'foobar');
    }

    public function testGetValueFromPrivateProperty()
    {
        $entity = new Entity('foobar');
        $metadata = new PropertyMetadata(self::CLASSNAME, 'internal');

        $this->assertEquals('foobar', $metadata->getValue($entity));
    }
}

