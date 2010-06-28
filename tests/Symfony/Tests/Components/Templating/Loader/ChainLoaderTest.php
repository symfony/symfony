<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Templating\Loader;

require_once __DIR__.'/../Fixtures/ProjectTemplateDebugger.php';

use Symfony\Components\Templating\Loader\ChainLoader;
use Symfony\Components\Templating\Loader\FilesystemLoader;
use Symfony\Components\Templating\Storage\FileStorage;

class ChainLoaderTest extends \PHPUnit_Framework_TestCase
{
    static protected $loader1, $loader2;

    static public function setUpBeforeClass()
    {
        $fixturesPath = realpath(__DIR__.'/../Fixtures/');
        self::$loader1 = new FilesystemLoader($fixturesPath.'/null/%name%');
        self::$loader2 = new FilesystemLoader($fixturesPath.'/templates/%name%.%renderer%');
    }

    public function testConstructor()
    {
        $loader = new ProjectTemplateLoader1(array(self::$loader1, self::$loader2));
        $this->assertEquals(array(self::$loader1, self::$loader2), $loader->getLoaders(), '__construct() takes an array of template loaders as its second argument');
    }

    public function testAddLoader()
    {
        $loader = new ProjectTemplateLoader1(array(self::$loader1));
        $loader->addLoader(self::$loader2);
        $this->assertEquals(array(self::$loader1, self::$loader2), $loader->getLoaders(), '->addLoader() adds a template loader at the end of the loaders');
    }

    public function testLoad()
    {
        $loader = new ProjectTemplateLoader1(array(self::$loader1, self::$loader2));
        $this->assertFalse($loader->load('bar'), '->load() returns false if the template is not found');
        $this->assertFalse($loader->load('foo', array('renderer' => 'xml')), '->load() returns false if the template does not exists for the given renderer');
        $this->assertInstanceOf('Symfony\Components\Templating\Storage\FileStorage', $loader->load('foo'), '->load() returns a FileStorage if the template exists');
    }
}

class ProjectTemplateLoader1 extends ChainLoader
{
    public function getLoaders()
    {
        return $this->loaders;
    }
}
