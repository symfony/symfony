<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Templating\Storage;

use Symfony\Component\Templating\Storage\Storage;
use Symfony\Component\Templating\Renderer\PhpRenderer;

class StorageTest extends \PHPUnit_Framework_TestCase
{
    public function testMagicToString()
    {
        $storage = new TestStorage('foo', 'php');
        $this->assertEquals('foo', (string) $storage, '__toString() returns the template name');
    }

    public function testGetRenderer()
    {
        $storage = new TestStorage('foo', 'php');
        $this->assertEquals('php', $storage->getRenderer(), '->getRenderer() returns the renderer');
    }
}

class TestStorage extends Storage
{
    public function getContent()
    {
    }
}
