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

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Loader\XliffFileLoader;

class XliffFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoad()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
        $this->assertSame(array(), libxml_get_errors());
    }

    public function testLoadWithInternalErrorsEnabled()
    {
        libxml_use_internal_errors(true);

        $this->assertSame(array(), libxml_get_errors());

        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
        $this->assertSame(array(), libxml_get_errors());
    }

    public function testLoadWithResname()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resname.xlf', 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo'), $catalogue->all('domain1'));
    }

    public function testIncompleteResource()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resources.xlf', 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar', 'key' => '', 'test' => 'with'), $catalogue->all('domain1'));
        $this->assertFalse($catalogue->has('extra', 'domain1'));
    }

    public function testEncoding()
    {
        if (!function_exists('iconv') && !function_exists('mb_convert_encoding')) {
            $this->markTestSkipped('The iconv and mbstring extensions are not available.');
        }

        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/encoding.xlf', 'en', 'domain1');

        $this->assertEquals(utf8_decode('föö'), $catalogue->get('bar', 'domain1'));
        $this->assertEquals(utf8_decode('bär'), $catalogue->get('foo', 'domain1'));
        $this->assertEquals(array('notes' => array(array('content' => utf8_decode('bäz')))), $catalogue->getMetadata('foo', 'domain1'));
    }

    /**
     * @expectedException \Symfony\Component\Translation\Exception\InvalidResourceException
     */
    public function testLoadInvalidResource()
    {
        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/resources.php', 'en', 'domain1');
    }

    /**
     * @expectedException \Symfony\Component\Translation\Exception\InvalidResourceException
     */
    public function testLoadResourceDoesNotValidate()
    {
        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/non-valid.xlf', 'en', 'domain1');
    }

    /**
     * @expectedException \Symfony\Component\Translation\Exception\NotFoundResourceException
     */
    public function testLoadNonExistingResource()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/non-existing.xlf';
        $loader->load($resource, 'en', 'domain1');
    }

    /**
     * @expectedException \Symfony\Component\Translation\Exception\InvalidResourceException
     */
    public function testLoadThrowsAnExceptionIfFileNotLocal()
    {
        $loader = new XliffFileLoader();
        $resource = 'http://example.com/resources.xlf';
        $loader->load($resource, 'en', 'domain1');
    }

    /**
     * @expectedException        \Symfony\Component\Translation\Exception\InvalidResourceException
     * @expectedExceptionMessage Document types are not allowed.
     */
    public function testDocTypeIsNotAllowed()
    {
        $loader = new XliffFileLoader();
        $loader->load(__DIR__.'/../fixtures/withdoctype.xlf', 'en', 'domain1');
    }

    public function testParseEmptyFile()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/empty.xlf';
        $this->setExpectedException('Symfony\Component\Translation\Exception\InvalidResourceException', sprintf('Unable to load "%s":', $resource));
        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadNotes()
    {
        $loader = new XliffFileLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/withnote.xlf', 'en', 'domain1');

        $this->assertEquals(array('notes' => array(array('priority' => 1, 'content' => 'foo'))), $catalogue->getMetadata('foo', 'domain1'));
        // message without target
        $this->assertNull($catalogue->getMetadata('extra', 'domain1'));
        $this->assertEquals(array('notes' => array(array('content' => 'baz'), array('priority' => 2, 'from' => 'bar', 'content' => 'qux'))), $catalogue->getMetadata('key', 'domain1'));
    }

    public function testLoadVersion2()
    {
        $loader = new XliffFileLoader();
        $resource = __DIR__.'/../fixtures/resources-2.0.xlf';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
        $this->assertSame(array(), libxml_get_errors());

        $domains = $catalogue->all();
        $this->assertCount(3, $domains['domain1']);

        // Notes aren't assigned to specific segments, but to whole units, so there's no way to do a mapping
        $this->assertEmpty($catalogue->getMetadata());
    }
}
