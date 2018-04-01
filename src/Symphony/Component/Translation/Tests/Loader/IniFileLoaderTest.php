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
use Symphony\Component\Translation\Loader\IniFileLoader;
use Symphony\Component\Config\Resource\FileResource;

class IniFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new IniFileLoader();
        $resource = __DIR__.'/../fixtures/resources.ini';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new IniFileLoader();
        $resource = __DIR__.'/../fixtures/empty.ini';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array(), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    /**
     * @expectedException \Symphony\Component\Translation\Exception\NotFoundResourceException
     */
    public function testLoadNonExistingResource()
    {
        $loader = new IniFileLoader();
        $resource = __DIR__.'/../fixtures/non-existing.ini';
        $loader->load($resource, 'en', 'domain1');
    }
}
