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

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Config\Resource\FileResource;

class YamlFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoad()
    {
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../fixtures/resources.yml';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../fixtures/empty.yml';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array(), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLoadThrowsAnExceptionIfNotAnArray()
    {
        $loader = new YamlFileLoader();
        $resource = __DIR__.'/../fixtures/non-valid.yml';
        $loader->load($resource, 'en', 'domain1');
    }
}
