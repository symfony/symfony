<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Translation\Loader;

use Symfony\Component\Translation\Loader\ResourceBundleLoader;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;

class ResourceBundleFileLoaderTest extends LocalizedTestCase
{
    public function testLoad()
    {
        // resource is build using genrb command
        $loader = new ResourceBundleLoader();
        $resource = __DIR__.'/../fixtures/resourcebundle/res';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new DirectoryResource($resource)), $catalogue->getResources());
    }

    public function testDatEnglishLoad()
    {
        // bundled resource is build using pkgdata command which at leas in ICU 4.2 comes in extremely! buggy form
        // you must specify an temporary build directory which is not the same as current directory and
        // MUST reside on the same partition. pkgdata -p resources -T /srv -d . packagelist.txt
        $loader = new ResourceBundleLoader();
        $resource = __DIR__.'/../fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('symfony' => 'Symfony 2 is great'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource.'.dat')), $catalogue->getResources());
    }

    public function testDatFrenchLoad()
    {
        $loader = new ResourceBundleLoader();
        $resource = __DIR__.'/../fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'fr', 'domain1');

        $this->assertEquals(array('symfony' => 'Symfony 2 est génial'), $catalogue->all('domain1'));
        $this->assertEquals('fr', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource.'.dat')), $catalogue->getResources());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLoadInvalidResource()
    {
        $loader = new ResourceBundleLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resourcebundle/res/en.txt', 'en', 'domain1');
    }
}
