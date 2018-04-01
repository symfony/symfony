<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Translation\Loader\QtFileLoader;
use Symphony\Component\Config\Resource\FileResource;

class QtFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new QtFileLoader();
        $resource = __DIR__.'/../fixtures/resources.ts';
        $catalogue = $loader->load($resource, 'en', 'resources');

        $this->assertEquals(array('foo' => 'bar'), $catalogue->all('resources'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    /**
     * @expectedException \Symphony\Component\Translation\Exception\NotFoundResourceException
     */
    public function testLoadNonExistingResource()
    {
        $loader = new QtFileLoader();
        $resource = __DIR__.'/../fixtures/non-existing.ts';
        $loader->load($resource, 'en', 'domain1');
    }

    /**
     * @expectedException \Symphony\Component\Translation\Exception\InvalidResourceException
     */
    public function testLoadNonLocalResource()
    {
        $loader = new QtFileLoader();
        $resource = 'http://domain1.com/resources.ts';
        $loader->load($resource, 'en', 'domain1');
    }

    /**
     * @expectedException \Symphony\Component\Translation\Exception\InvalidResourceException
     */
    public function testLoadInvalidResource()
    {
        $loader = new QtFileLoader();
        $resource = __DIR__.'/../fixtures/invalid-xml-resources.xlf';
        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadEmptyResource()
    {
        $loader = new QtFileLoader();
        $resource = __DIR__.'/../fixtures/empty.xlf';

        if (method_exists($this, 'expectException')) {
            $this->expectException('Symphony\Component\Translation\Exception\InvalidResourceException');
            $this->expectExceptionMessage(sprintf('Unable to load "%s".', $resource));
        } else {
            $this->setExpectedException('Symphony\Component\Translation\Exception\InvalidResourceException', sprintf('Unable to load "%s".', $resource));
        }

        $loader->load($resource, 'en', 'domain1');
    }
}
