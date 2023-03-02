<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;

class IniFileLoaderTest extends TestCase
{
    protected $container;
    protected $loader;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->loader = new IniFileLoader($this->container, new FileLocator(realpath(__DIR__.'/../Fixtures/').'/ini'));
    }

    public function testIniFileCanBeLoaded()
    {
        $this->loader->load('parameters.ini');
        $this->assertEquals(['foo' => 'bar', 'bar' => '%foo%'], $this->container->getParameterBag()->all(), '->load() takes a single file name as its first argument');
    }

    /**
     * @dataProvider getTypeConversions
     */
    public function testTypeConversions($key, $value, $supported)
    {
        $this->loader->load('types.ini');
        $parameters = $this->container->getParameterBag()->all();
        $this->assertSame($value, $parameters[$key], '->load() converts values to PHP types');
    }

    /**
     * @dataProvider getTypeConversions
     * This test illustrates where our conversions differs from INI_SCANNER_TYPED introduced in PHP 5.6.1
     */
    public function testTypeConversionsWithNativePhp($key, $value, $supported)
    {
        if (!$supported) {
            $this->markTestSkipped(sprintf('Converting the value "%s" to "%s" is not supported by the IniFileLoader.', $key, $value));
        }

        $expected = parse_ini_file(__DIR__.'/../Fixtures/ini/types.ini', true, \INI_SCANNER_TYPED);
        $this->assertSame($value, $expected['parameters'][$key], '->load() converts values to PHP types');
    }

    public static function getTypeConversions()
    {
        return [
            ['true_comment', true, true],
            ['true', true, true],
            ['false', false, true],
            ['on', true, true],
            ['off', false, true],
            ['yes', true, true],
            ['no', false, true],
            ['none', false, true],
            ['null', null, true],
            ['constant', \PHP_VERSION, true],
            ['12_int', 12, true],
            ['12_string', '12', true],
            ['12_quoted_number', 12, false], // INI_SCANNER_RAW removes the double quotes
            ['12_comment', 12, true],
            ['12_string_comment', '12', true],
            ['12_quoted_number_comment', 12, false], // INI_SCANNER_RAW removes the double quotes
            ['-12_negative', -12, true],
            ['one', 1, true],
            ['zero', 0, true],
            ['0b0110_byte_string', bindec('0b0110'), false], // not supported by INI_SCANNER_TYPED
            ['11112222333344445555_great_number', '1111,2222,3333,4444,5555', true],
            ['0777_number_starting_with_0', 0777, false], // not supported by INI_SCANNER_TYPED
            ['255_hexadecimal', 0xFF, false], // not supported by INI_SCANNER_TYPED
            ['100.0_exponential', 1e2, false], // not supported by INI_SCANNER_TYPED
            ['-120.0_exponential', -1.2E2, false], // not supported by INI_SCANNER_TYPED
            ['-10100.1_negative_float', -10100.1, false], // not supported by INI_SCANNER_TYPED
            ['-10,100.1_negative_float', '-10,100.1', true],
            ['list', [1, 2], true],
            ['map', ['one' => 1, 'two' => 2], true],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider getLegacyTypeConversions
     */
    public function testLegacyTypeConversionsWithNativePhp($key, $value, $supported)
    {
        if (!$supported) {
            $this->markTestSkipped(sprintf('Converting the value "%s" to "%s" is not supported by the IniFileLoader.', $key, $value));
        }

        $expected = parse_ini_file(__DIR__.'/../Fixtures/ini/types_legacy.ini', true, \INI_SCANNER_TYPED);
        $this->assertSame($value, $expected['parameters'][$key], '->load() converts values to PHP types');
    }

    public static function getLegacyTypeConversions()
    {
        return [
            ['true_comment', true, true],
            ['true', true, true],
            ['false', false, true],
            ['on', true, true],
            ['off', false, true],
            ['yes', true, true],
            ['no', false, true],
            ['none', false, true],
            ['null', null, true],
            ['constant', \PHP_VERSION, true],
            ['12', 12, true],
            ['12_string', '12', true],
            ['12_quoted_number', 12, false], // INI_SCANNER_RAW removes the double quotes
            ['12_comment', 12, true],
            ['12_string_comment', '12', true],
            ['12_quoted_number_comment', 12, false], // INI_SCANNER_RAW removes the double quotes
            ['-12', -12, true],
            ['1', 1, true],
            ['0', 0, true],
            ['0b0110', bindec('0b0110'), false], // not supported by INI_SCANNER_TYPED
            ['11112222333344445555', '1111,2222,3333,4444,5555', true],
            ['0777', 0777, false], // not supported by INI_SCANNER_TYPED
            ['255', 0xFF, false], // not supported by INI_SCANNER_TYPED
            ['100.0', 1e2, false], // not supported by INI_SCANNER_TYPED
            ['-120.0', -1.2E2, false], // not supported by INI_SCANNER_TYPED
            ['-10100.1', -10100.1, false], // not supported by INI_SCANNER_TYPED
            ['-10,100.1', '-10,100.1', true],
            ['list', [1, 2], true],
            ['map', ['one' => 1, 'two' => 2], true],
        ];
    }

    public function testExceptionIsRaisedWhenIniFileDoesNotExist()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The file "foo.ini" does not exist (in:');
        $this->loader->load('foo.ini');
    }

    public function testExceptionIsRaisedWhenIniFileCannotBeParsed()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "nonvalid.ini" file is not valid.');
        @$this->loader->load('nonvalid.ini');
    }

    public function testExceptionIsRaisedWhenIniFileIsAlmostValid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "almostvalid.ini" file is not valid.');
        @$this->loader->load('almostvalid.ini');
    }

    public function testSupports()
    {
        $loader = new IniFileLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('foo.ini'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns false if the resource is not loadable');
        $this->assertTrue($loader->supports('with_wrong_ext.yml', 'ini'), '->supports() returns true if the resource with forced type is loadable');
    }

    public function testWhenEnv()
    {
        $container = new ContainerBuilder();
        $loader = new IniFileLoader($container, new FileLocator(realpath(__DIR__.'/../Fixtures/').'/ini'), 'some-env');
        $loader->load('when-env.ini');

        $this->assertSame(['foo' => 234, 'bar' => 345], $container->getParameterBag()->all());
    }
}
