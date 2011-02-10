<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Translation\Resource;

use Symfony\Component\Translation\Resource\FileResource;

class FileResourceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructor()
    {
        $resource = new FileResource(__DIR__.'/../fixtures/foobar');
    }

    public function testMagicToString()
    {
        $resource = new FileResource(__DIR__.'/../fixtures/resources.php');

        $this->assertEquals(realpath(__DIR__.'/../fixtures/resources.php'), (string) $resource);
    }

    public function testGetResource()
    {
        $resource = new FileResource(__DIR__.'/../fixtures/resources.php');

        $this->assertEquals(realpath(__DIR__.'/../fixtures/resources.php'), $resource->getResource());
    }

    public function testIsUptodate()
    {
        $r = __DIR__.'/../fixtures/resources.php';
        $resource = new FileResource($r);

        $this->assertFalse($resource->isUptodate(filemtime($r) - 100));
        $this->assertTrue($resource->isUptodate(filemtime($r) + 100));
    }
}
