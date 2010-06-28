<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Loader\IniFileLoader;

class IniFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    /**
     * @covers Symfony\Components\DependencyInjection\Loader\IniFileLoader::__construct
     * @covers Symfony\Components\DependencyInjection\Loader\IniFileLoader::load
     */
    public function testLoader()
    {
        $loader = new IniFileLoader(self::$fixturesPath.'/ini');
        $config = $loader->load('parameters.ini');
        $this->assertEquals(array('foo' => 'bar', 'bar' => '%foo%'), $config->getParameterBag()->all(), '->load() takes a single file name as its first argument');

        try {
            $loader->load('foo.ini');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not exist');
            $this->assertStringStartsWith('The file "foo.ini" does not exist (in: ', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not exist');
        }

        try {
            @$loader->load('nonvalid.ini');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file is not parseable');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file is not parseable');
            $this->assertEquals('The nonvalid.ini file is not valid.', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file is not parseable');
        }
    }
}
