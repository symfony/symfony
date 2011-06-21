<?php

namespace Symfony\Tests\Component\Serializer\Normalizer;

require_once __DIR__.'/../Fixtures/ScalarDummy.php';

use Symfony\Tests\Component\Serializer\Fixtures\ScalarDummy;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Serializer;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class CustomNormalizerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->normalizer = new CustomNormalizer;
        $this->normalizer->setSerializer(new Serializer);
    }

    public function testSerialize()
    {
        $obj = new ScalarDummy;
        $obj->foo = 'foo';
        $obj->xmlFoo = 'xml';
        $this->assertEquals('foo', $this->normalizer->normalize($obj, 'json'));
        $this->assertEquals('xml', $this->normalizer->normalize($obj, 'xml'));
    }

    public function testDeserialize()
    {
        $obj = $this->normalizer->denormalize('foo', get_class(new ScalarDummy), 'xml');
        $this->assertEquals('foo', $obj->xmlFoo);
        $this->assertNull($obj->foo);

        $obj = $this->normalizer->denormalize('foo', get_class(new ScalarDummy), 'json');
        $this->assertEquals('foo', $obj->foo);
        $this->assertNull($obj->xmlFoo);
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new ScalarDummy));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass));
    }

    public function testSupportsDenormalization()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization(array(), 'Symfony\Tests\Component\Serializer\Fixtures\ScalarDummy'));
        $this->assertFalse($this->normalizer->supportsDenormalization(array(), 'stdClass'));
    }
}
