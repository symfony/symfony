<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Config\Resource\FileResource;

class XliffFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Config\Loader\Loader')) {
            $this->markTestSkipped('The "Config" component is not available');
        }
    }

    public function testLoad()
    {
        $loader = $this->createLoader();
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadWithResname()
    {
        $loader = $this->createLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resname.xlf', 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo'), $catalogue->all('domain1'));
    }

    public function testIncompleteResource()
    {
        $loader = $this->createLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resources.xlf', 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar', 'key' => '', 'test' => 'with'), $catalogue->all('domain1'));
        $this->assertFalse($catalogue->has('extra', 'domain1'));
    }

    public function testEncoding()
    {
        if (!function_exists('iconv') && !function_exists('mb_convert_encoding')) {
            $this->markTestSkipped('The iconv and mbstring extensions are not available.');
        }

        $loader = $this->createLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/encoding.xlf', 'en', 'domain1');

        $this->assertEquals(utf8_decode('föö'), $catalogue->get('bar', 'domain1'));
        $this->assertEquals(utf8_decode('bär'), $catalogue->get('foo', 'domain1'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLoadInvalidResource()
    {
        $loader = $this->createLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resources.php', 'en', 'domain1');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLoadResourceDoesNotValidate()
    {
        $loader = $this->createLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/non-valid.xlf', 'en', 'domain1');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLoadThrowsAnExceptionIfFileNotLocal()
    {
        $loader = $this->createLoader();
        $resource = 'http://example.com/resources.xlf';
        $loader->load($resource, 'en', 'domain1');
    }

    /**
     * @expectedException        \RuntimeException
     * @expectedExceptionMessage Document types are not allowed.
     */
    public function testDocTypeIsNotAllowed()
    {
        $loader = $this->createLoader();
        $loader->load(__DIR__.'/../fixtures/withdoctype.xlf', 'en', 'domain1');
    }

    protected function createLoader()
    {
        return new XliffFileLoader();
    }
}
