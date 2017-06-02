<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ValueExporter\Tests;

use Symfony\Component\ValueExporter\Formatter\TraversableToStringFormatter;
use Symfony\Component\ValueExporter\Tests\Fixtures\Entity;
use Symfony\Component\ValueExporter\Tests\Fixtures\EntityImplementingToString;
use Symfony\Component\ValueExporter\Tests\Fixtures\ObjectImplementingToString;
use Symfony\Component\ValueExporter\Tests\Fixtures\PublicEntity;
use Symfony\Component\ValueExporter\Tests\Fixtures\TraversableInstance;
use Symfony\Component\ValueExporter\ValueExporter;

class ValueExporterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider valueProvider
     */
    public function testExportValue($value, $string)
    {
        $this->assertSame($string, ValueExporter::export($value));
    }

    /**
     * @dataProvider valueProvider
     */
    public function testToStringFunctionWrapper($value, $string)
    {
        $this->assertSame($string, to_string($value));
    }

    public function testExportValueExpanded()
    {
        $value = array(
            array(ValueExporter::class, 'export'),
        );

        $exportedValue = <<<EOT
array(
  0 => (static) "Symfony\Component\ValueExporter\ValueExporter::export"
)
EOT;

        $this->assertSame($exportedValue, ValueExporter::export($value, 1, true));
    }

    public function testExportTraversable()
    {
        ValueExporter::addFormatters(array(TraversableToStringFormatter::class));

        $value = new TraversableInstance();
        $exportedValue = <<<EOT
Traversable:"Symfony\Component\ValueExporter\Tests\Fixtures\TraversableInstance"(
  'property1' => "value1",
  'property2' => "value2"
)
EOT;

        $this->assertSame($exportedValue, ValueExporter::export($value));
    }

    public function valueProvider()
    {
        $foo = new \__PHP_Incomplete_Class();
        $array = new \ArrayObject($foo);
        $array['__PHP_Incomplete_Class_Name'] = 'AppBundle/Foo';

        return array(
            'null' => array(null, 'null'),
            'true' => array(true, 'true'),
            'false' => array(false, 'false'),
            'int' => array(4, '(int) 4'),
            'float' => array(4.5, '(float) 4.5'),
            'string' => array('test', '"test"'),
            'empty array' => array(array(), 'array()'),
            'numeric array' => array(
                array(0 => null, 1 => true, 2 => 1, 3 => '2', 4 => new \stdClass()),
                'array(0 => null, 1 => true, 2 => (int) 1, 3 => "2", 4 => object(stdClass))',
            ),
            'mixed keys array' => array(
                array(0 => 0, '1' => 'un', 'key' => 4.5),
                'array(0 => (int) 0, 1 => "un", \'key\' => (float) 4.5)',
            ),
            'object implementing to string' => array(
                new ObjectImplementingToString('test'),
                'object(Symfony\Component\ValueExporter\Tests\Fixtures\ObjectImplementingToString) "test"',
            ),
            'closure' => array(function() {}, 'object(Closure)'),
            'callable string' => array('strlen', '(function) "strlen"'),
            'callable array' => array(
                array($this, 'testExportValue'),
                '(callable) "Symfony\Component\ValueExporter\Tests\ValueExporterTest::testExportValue"',
            ),
            'invokable object' => array($this, '(invokable) "Symfony\Component\ValueExporter\Tests\ValueExporterTest"'),
            'invokable object as array' => array(array($this, '__invoke'), '(invokable) "Symfony\Component\ValueExporter\Tests\ValueExporterTest"'),
            'datetime' => array(
                new \DateTime('2014-06-10 07:35:40', new \DateTimeZone('UTC')),
                'object(DateTime) - 2014-06-10T07:35:40+0000',
            ),
            'datetime immutable' => array(
                new \DateTimeImmutable('2014-06-10 07:35:40', new \DateTimeZone('UTC')),
                'object(DateTimeImmutable) - 2014-06-10T07:35:40+0000',
            ),
            'php incomplete class' => array($foo, '__PHP_Incomplete_Class(AppBundle/Foo)'),
            'entity' => array(new Entity(23), 'entity:23(Symfony\Component\ValueExporter\Tests\Fixtures\Entity)'),
            'public entity' => array(new PublicEntity(23), 'entity:23(Symfony\Component\ValueExporter\Tests\Fixtures\PublicEntity)'),
            'entity implementing to string' => array(
                new EntityImplementingToString(23, 'test'),
                'entity:23(Symfony\Component\ValueExporter\Tests\Fixtures\EntityImplementingToString) "test"',
            ),
        );
    }

    public function __invoke()
    {
        return 'TEST';
    }
}
