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

use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Config\Resource\FileResource;

class XliffFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoad()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources.xliff';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar'), $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    /**
     * @expectedException Exception
     */
    public function testLoadInvalidResource()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resources.php', 'en', 'domain1');
    }

    /**
     * @expectedException Exception
     */
    public function testLoadResourceDoesNotValidate()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/non-valid.xliff', 'en', 'domain1');
    }
}
