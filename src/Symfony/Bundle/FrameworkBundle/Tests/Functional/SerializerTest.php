<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SerializerTest extends WebTestCase
{
    public function testDeserializeArrayOfObject()
    {
        if (!class_exists(DataUriNormalizer::class)) {
            $this->markTestSkipped('This test is only applicable when using the Symfony Serializer Component version 3.1 or superior.');
        }

        static::bootKernel(array('test_case' => 'Serializer'));
        $container = static::$kernel->getContainer();

        $result = $container->get('serializer')->deserialize('{"bars": [{"id": 1}, {"id": 2}]}', Foo::class, 'json');

        $bar1 = new Bar();
        $bar1->id = 1;
        $bar2 = new Bar();
        $bar2->id = 2;

        $expected = new Foo();
        $expected->bars = array($bar1, $bar2);

        $this->assertEquals($expected, $result);
    }
}

class Foo
{
    /**
     * @var Bar[]
     */
    public $bars;
}

class Bar
{
    /**
     * @var int
     */
    public $id;
}
