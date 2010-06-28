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

use Symfony\Components\Templating\Loader\Loader;
use Symfony\Components\Templating\Loader\CacheLoader;
use Symfony\Components\Templating\Storage\StringStorage;

class CacheLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $loader = new ProjectTemplateLoader($varLoader = new ProjectTemplateLoaderVar(), sys_get_temp_dir());
        $this->assertTrue($loader->getLoader() === $varLoader, '__construct() takes a template loader as its first argument');
        $this->assertEquals(sys_get_temp_dir(), $loader->getDir(), '__construct() takes a directory where to store the cache as its second argument');
    }

    public function testLoad()
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.rand(111111, 999999);
        mkdir($dir, 0777, true);

        $loader = new ProjectTemplateLoader($varLoader = new ProjectTemplateLoaderVar(), $dir);
        $loader->setDebugger($debugger = new \ProjectTemplateDebugger());
        $this->assertFalse($loader->load('foo'), '->load() returns false if the embed loader is not able to load the template');
        $loader->load('index');
        $this->assertTrue($debugger->hasMessage('Storing template'), '->load() logs a "Storing template" message if the template is found');
        $loader->load('index');
        $this->assertTrue($debugger->hasMessage('Fetching template'), '->load() logs a "Storing template" message if the template is fetched from cache');
    }
}

class ProjectTemplateLoader extends CacheLoader
{
    public function getDir()
    {
        return $this->dir;
    }

    public function getLoader()
    {
        return $this->loader;
    }
}

class ProjectTemplateLoaderVar extends Loader
{
    public function getIndexTemplate()
    {
        return 'Hello World';
    }

    public function getSpecialTemplate()
    {
        return 'Hello {{ name }}';
    }

    public function load($template, array $options = array())
    {
        if (method_exists($this, $method = 'get'.ucfirst($template).'Template')) {
            return new StringStorage($this->$method());
        }

        return false;
    }

    public function isFresh($template, array $options = array(), $time)
    {
        return false;
    }
}
