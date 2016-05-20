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
use Symfony\Component\Config\Resource\FileResource;

class ResourceBundleFileLoaderTest extends LocalizedTestCase
{
    public function testLoad()
    {
        $loader = new ResourceBundleLoader();
        $resource = __DIR__.'/../fixtures/resourcebundle/res';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testDatEnglishLoad()
    {
        $loader = new ResourceBundleLoader();
        $resource = __DIR__.'/../fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('symfony' => 'Symfony 2 is great'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testDatFrenchLoad()
    {
        $loader = new ResourceBundleLoader();
        $resource = __DIR__.'/../fixtures/resourcebundle/dat/resources';
        $catalogue = $loader->load($resource, 'fr', 'domain1');

        $this->assertEquals(array('symfony' => 'Symfony 2 est génial'), $catalogue->all('domain1'));
        $this->assertEquals('fr', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    /**
     * @expectedException Exception
     */
    public function testLoadInvalidResource()
    {
        $loader = new ResourceBundleLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resourcebundle/res/en.txt', 'en', 'domain1');
    }
}
