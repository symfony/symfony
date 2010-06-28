<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Routing\Resource;

use Symfony\Components\Routing\Resource\FileResource;

class FileResourceTest extends \PHPUnit_Framework_TestCase
{
    public function testGetResource()
    {
        $file = sys_get_temp_dir().'/tmp.xml';
        touch($file);
        $resource = new FileResource($file);
        $this->assertEquals(realpath($file), $resource->getResource(), '->getResource() returns the path to the resource');
        unlink($file);
    }

    public function testIsUptodate()
    {
        $file = sys_get_temp_dir().'/tmp.xml';
        touch($file);
        $resource = new FileResource($file);
        $this->assertTrue($resource->isUptodate(time() + 10), '->isUptodate() returns true if the resource has not changed');
        $this->assertTrue(!$resource->isUptodate(time() - 86400), '->isUptodate() returns false if the resource has been updated');

        $resource = new FileResource('/____foo/foobar'.rand(1, 999999));
        $this->assertTrue(!$resource->isUptodate(time()), '->isUptodate() returns false if the resource does not exist');
    }
}
