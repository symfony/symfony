<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;

class PropertyNormalizerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var PropertyNormalizer
     */
    private $normalizer;

    protected function setUp()
    {
        $this->normalizer = new PropertyNormalizer();
        $this->normalizer->setSerializer($this->getMock('Symfony\Component\Serializer\Serializer'));
    }

    public function testNormalize()
    {
        $obj = new PropertyDummy();
        $obj->foo = 'foo';
        $obj->setBar('bar');
        $obj->setCamelCase('camelcase');
        $this->assertEquals(
            array('foo' => 'foo', 'bar' => 'bar', 'camelCase' => 'camelcase'),
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function testDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'foo', 'bar' => 'bar'),
            __NAMESPACE__.'\PropertyDummy',
            'any'
        );
        $this->assertEquals('foo', $obj->foo);
        $this->assertEquals('bar', $obj->getBar());
    }

    public function testDenormalizeOnCamelCaseFormat()
    {
        $this->normalizer->setCamelizedAttributes(array('camel_case'));
        $obj = $this->normalizer->denormalize(
            array('camel_case' => 'value'),
            __NAMESPACE__.'\PropertyDummy'
        );
        $this->assertEquals('value', $obj->getCamelCase());
    }

    /**
     * @dataProvider attributeProvider
     */
    public function testFormatAttribute($attribute, $camelizedAttributes, $result)
    {
        $r = new \ReflectionObject($this->normalizer);
        $m = $r->getMethod('formatAttribute');
        $m->setAccessible(true);

        $this->normalizer->setCamelizedAttributes($camelizedAttributes);
        $this->assertEquals($m->invoke($this->normalizer, $attribute, $camelizedAttributes), $result);
    }

    public function attributeProvider()
    {
        return array(
            array('attribute_test', array('attribute_test'),'AttributeTest'),
            array('attribute_test', array('any'),'attribute_test'),
            array('attribute', array('attribute'),'Attribute'),
            array('attribute', array(), 'attribute'),
        );
    }

    public function testConstructorDenormalize()
    {
        $obj = $this->normalizer->denormalize(
            array('foo' => 'foo', 'bar' => 'bar'),
            __NAMESPACE__.'\PropertyConstructorDummy',
            'any'
        );
        $this->assertEquals('foo', $obj->getFoo());
        $this->assertEquals('bar', $obj->getBar());
    }

    /**
     * @dataProvider provideCallbacks
     */
    public function testCallbacks($callbacks, $value, $result, $message)
    {
        $this->normalizer->setCallbacks($callbacks);

        $obj = new PropertyConstructorDummy('', $value);

        $this->assertEquals(
            $result,
            $this->normalizer->normalize($obj, 'any'),
            $message
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testUncallableCallbacks()
    {
        $this->normalizer->setCallbacks(array('bar' => null));

        $obj = new PropertyConstructorDummy('baz', 'quux');

        $this->normalizer->normalize($obj, 'any');
    }

    public function testIgnoredAttributes()
    {
        $this->normalizer->setIgnoredAttributes(array('foo', 'bar', 'camelCase'));

        $obj = new PropertyDummy();
        $obj->foo = 'foo';
        $obj->setBar('bar');

        $this->assertEquals(
            array(),
            $this->normalizer->normalize($obj, 'any')
        );
    }

    public function provideCallbacks()
    {
        return array(
            array(
                array(
                    'bar' => function ($bar) {
                        return 'baz';
                    },
                ),
                'baz',
                array('foo' => '', 'bar' => 'baz'),
                'Change a string',
            ),
            array(
                array(
                    'bar' => function ($bar) {
                        return null;
                    },
                ),
                'baz',
                array('foo' => '', 'bar' => null),
                'Null an item'
            ),
            array(
                array(
                    'bar' => function ($bar) {
                        return $bar->format('d-m-Y H:i:s');
                    },
                ),
                new \DateTime('2011-09-10 06:30:00'),
                array('foo' => '', 'bar' => '10-09-2011 06:30:00'),
                'Format a date',
            ),
            array(
                array(
                    'bar' => function ($bars) {
                        $foos = '';
                        foreach ($bars as $bar) {
                            $foos .= $bar->getFoo();
                        }

                        return $foos;
                    },
                ),
                array(new PropertyConstructorDummy('baz', ''), new PropertyConstructorDummy('quux', '')),
                array('foo' => '', 'bar' => 'bazquux'),
                'Collect a property',
            ),
            array(
                array(
                    'bar' => function ($bars) {
                        return count($bars);
                    },
                ),
                array(new PropertyConstructorDummy('baz', ''), new PropertyConstructorDummy('quux', '')),
                array('foo' => '', 'bar' => 2),
                'Count a property',
            ),
        );
    }
}

class PropertyDummy
{
    public $foo;
    private $bar;
    protected $camelCase;

    public function getBar()
    {
        return $this->bar;
    }

    public function setBar($bar)
    {
        $this->bar = $bar;
    }

    public function getCamelCase()
    {
        return $this->camelCase;
    }

    public function setCamelCase($camelCase)
    {
        $this->camelCase = $camelCase;
    }
}

class PropertyConstructorDummy
{
    protected $foo;
    private $bar;

    public function __construct($foo, $bar)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }

    public function getFoo()
    {
        return $this->foo;
    }

    public function getBar()
    {
        return $this->bar;
    }
}
