<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Framework\FoundationBundle\Tests\Util;

use Symfony\Framework\FoundationBundle\Tests\TestCase;
use Symfony\Framework\FoundationBundle\Util\Mustache;
use Symfony\Framework\FoundationBundle\Util\Filesystem;

class MustacheTest extends TestCase
{
    protected $dir;

    public function setUp()
    {
        $dir = __DIR__.'/fixtures/';

        $this->dir = sys_get_temp_dir().'/mustache';
        $filesystem = new Filesystem();
        $filesystem->mirror($dir, $this->dir);
    }

    public function tearDown()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->dir);
    }

    public function testRenderString()
    {
        $template = 'Hi {{ you }}, my name is {{ me }}!';
        $expected = 'Hi {{ you }}, my name is Kris!';

        $this->assertEquals(Mustache::renderString($template, array('me' => 'Kris')), $expected, '::renderString() does not modify unknown parameters');
    }

    public function testRenderFile()
    {
        Mustache::renderFile($this->dir.'/template.txt', array('me' => 'Fabien'));

        $this->assertEquals('Hello Fabien', file_get_contents($this->dir.'/template.txt'), '::renderFile() renders a file');
    }

    public function testRenderDir()
    {
        Mustache::renderDir($this->dir, array('me' => 'Fabien'));

        $this->assertEquals('Hello Fabien', file_get_contents($this->dir.'/template.txt'), '::renderDir() renders a directory');
        $this->assertEquals('Hello Fabien', file_get_contents($this->dir.'/foo/bar.txt'), '::renderDir() renders a directory');
    }
}
