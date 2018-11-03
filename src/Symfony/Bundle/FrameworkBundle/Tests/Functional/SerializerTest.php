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

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class SerializerTest extends WebTestCase
{
    public function testDeserializeArrayOfObject()
    {
        static::bootKernel(array('test_case' => 'Serializer'));

        $result = static::$container->get('serializer')->deserialize('{"bars": [{"id": 1}, {"id": 2}]}', Foo::class, 'json');

        $bar1 = new Bar();
        $bar1->id = 1;
        $bar2 = new Bar();
        $bar2->id = 2;

        $expected = new Foo();
        $expected->bars = array($bar1, $bar2);

        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider caseProvider
     */
    public function testSerializeArrayOfObject($testCase)
    {
        static::bootKernel(array('test_case' => $testCase));
        $container = static::$kernel->getContainer();

        $bar1 = new Bar();
        $bar1->id = 1;
        $bar2 = new Bar();
        $bar2->id = 2;

        $foo = new Foo();
        $foo->bars = array($bar1, $bar2);

        $result = $container->get('serializer')->normalize($foo);

        $this->assertEquals(array('bars' => array(array('id' => 1), array('id' => 2))), $result);
    }

    public function caseProvider()
    {
        return array(array('Serializer'), array('GeneratedSerializer'));
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
