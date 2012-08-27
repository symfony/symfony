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
     * @expectedException \RuntimeException
     */
    public function testLoadInvalidResource()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resources.php', 'en', 'domain1');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLoadResourceDoesNotValidate()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/non-valid.xliff', 'en', 'domain1');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLoadThrowsAnExceptionIfFileNotLocal()
    {
        $loader = new XliffFileLoader();
        $resource = 'http://example.com/resources.xliff';
        $loader->load($resource, 'en', 'domain1');
    }

    /**
     * @expectedException        \RuntimeException
     * @expectedExceptionMessage Document types are not allowed.
     */
    public function testDocTypeIsNotAllowed()
    {
        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/withdoctype.xliff', 'en', 'domain1');
    }
}
