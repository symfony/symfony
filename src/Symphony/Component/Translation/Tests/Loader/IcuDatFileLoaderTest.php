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

use Symphony\Component\Translation\Loader\IcuDatFileLoader;
use Symphony\Component\Config\Resource\FileResource;

/**
 * @requires extension intl
 */
class IcuDatFileLoaderTest extends LocalizedTestCase
{
    /**
     * @expectedException \Symphony\Component\Translation\Exception\InvalidResourceException
     */
    public function testLoadInvalidResource()
    {
        $loader = new IcuDatFileLoader();
        $loader->load(__DIR__.'/../fixtures/resourcebundle/corrupted/resources', 'es', 'domain2');
    }

    public function testDatEnglishLoad()
    {
        // bundled resource is build using pkgdata command which at least in ICU 4.2 comes in extremely! buggy form
        // you must specify an temporary build directory which is not the same as current directory and
        // MUST reside on the same partition. pkgdata -p resources -T /srv -d.packagelist.txt
        $loader = new IcuDatFileLoader();
        $resource = __DIR__.'/../fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('symphony' => 'Symphony 2 is great'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource.'.dat')), $catalogue->getResources());
    }

    public function testDatFrenchLoad()
    {
        $loader = new IcuDatFileLoader();
        $resource = __DIR__.'/../fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'fr', 'domain1');

        $this->assertEquals(array('symphony' => 'Symphony 2 est génial'), $catalogue->all('domain1'));
        $this->assertEquals('fr', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource.'.dat')), $catalogue->getResources());
    }

    /**
     * @expectedException \Symphony\Component\Translation\Exception\NotFoundResourceException
     */
    public function testLoadNonExistingResource()
    {
        $loader = new IcuDatFileLoader();
        $loader->load(__DIR__.'/../fixtures/non-existing.txt', 'en', 'domain1');
    }
}
