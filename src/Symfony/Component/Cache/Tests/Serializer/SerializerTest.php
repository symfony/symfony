<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\SerializerInterface;

abstract class SerializerTest extends TestCase
{
    /** @var SerializerInterface */
    private $serializer;

    protected function setUp()
    {
        parent::setUp();

        $this->serializer = $this->createSerializer();
    }

    abstract protected function createSerializer(): SerializerInterface;

    /**
     * @dataProvider validData
     */
    public function testSerializeInvariants($value)
    {
        $serialized = $this->serializer->serialize($value);
        $restored = $this->serializer->unserialize($serialized);
        $this->assertEquals($value, $restored);
    }

    /**
     * Data provider for valid data to store.
     *
     * @return array
     */
    public static function validData()
    {
        return array(
            array(false),
            array('AbC19_.'),
            array(4711),
            array(47.11),
            array(true),
            array(null),
            array(array('key' => 'value')),
            array(new \stdClass()),
        );
    }
}

class NotUnserializable implements \Serializable
{
    public function serialize()
    {
        return serialize(123);
    }

    public function unserialize($ser)
    {
        throw new \Exception(__CLASS__);
    }
}
