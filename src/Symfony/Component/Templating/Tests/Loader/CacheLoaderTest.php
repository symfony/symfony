<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Loader;

use Symfony\Component\Templating\Loader\Loader;
use Symfony\Component\Templating\Loader\CacheLoader;
use Symfony\Component\Templating\Storage\StringStorage;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\TemplateReferenceInterface;
use Symfony\Component\Templating\TemplateReference;

class CacheLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $loader = new ProjectTemplateLoader($varLoader = new ProjectTemplateLoaderVar(new TemplateNameParser()), sys_get_temp_dir());
        $this->assertTrue($loader->getLoader() === $varLoader, '__construct() takes a template loader as its first argument');
        $this->assertEquals(sys_get_temp_dir(), $loader->getDir(), '__construct() takes a directory where to store the cache as its second argument');
    }

    public function testLoad()
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.rand(111111, 999999);
        mkdir($dir, 0777, true);

        $loader = new ProjectTemplateLoader($varLoader = new ProjectTemplateLoaderVar(new TemplateNameParser()), $dir);
        $this->assertFalse($loader->load(new TemplateReference('foo', 'php')), '->load() returns false if the embed loader is not able to load the template');

        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $logger->expects($this->once())->method('debug')->with('Storing template "index" in cache');
        $loader->setLogger($logger);
        $loader->load(new TemplateReference('index'));

        $logger = $this->getMock('Psr\Log\LoggerInterface');
        $logger->expects($this->once())->method('debug')->with('Fetching template "index" from cache');
        $loader->setLogger($logger);
        $loader->load(new TemplateReference('index'));
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

    public function load(TemplateReferenceInterface $template)
    {
        if (method_exists($this, $method = 'get'.ucfirst($template->get('name')).'Template')) {
            return new StringStorage($this->$method());
        }

        return false;
    }

    public function isFresh(TemplateReferenceInterface $template, $time)
    {
        return false;
    }
}
