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

use Symfony\Component\Translation\Loader\PoFileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Gettext;

class PoFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Config\Loader\Loader')) {
            $this->markTestSkipped('The "Config" component is not available');
        }
    }

    public function testLoad()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/resources.po';
        $catalogue = $loader->load($resource, 'en');
        $this->assertEquals(array('foo' => 'bar'), $catalogue->all('messages'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadPlurals()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/plurals.po';
        $catalogue = $loader->load($resource, 'en');

        $this->assertEquals(array('foo' => 'bar', 'foos' => 'bar|bars', 'foo|foos' => 'bar|bars'), $catalogue->all('messages'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadDoesNothingIfEmpty()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/empty.po';
        $catalogue = $loader->load($resource, 'en');

        $this->assertEquals(array(), $catalogue->all('messages'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals(array(new FileResource($resource)), $catalogue->getResources());
    }

    public function testLoadMultiline()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/multiline.po';
        $catalogue = $loader->load($resource, 'en');

        $this->assertEquals(3, count($catalogue->all('messages')));

        $messages = $catalogue->all('messages');
        $this->assertEquals('trans single line', $messages['both single line']);
        $this->assertEquals('trans multi line', $messages['source single line']);
        $this->assertEquals('trans single line', $messages['source multi line']);

    }

    /**
     * Read file with one item without whitespaces before and after.
     */
    public function testLoadMinimalFile()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/minimal.po';
        $catalogue = $loader->load($resource, 'en');
        // TODO: This fails on 'source multi line'
        $this->assertEquals(1, count($catalogue->all('messages')));
    }

    /**
     * Read the PO header and check it's available.
     */
    public function testLoadHeader()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/header.po';
        $catalogue = $loader->load($resource, 'en');
        $messages = $catalogue->all('messages');
        $this->assertEquals(1, count($catalogue->all('messages')));
        // Header exists
        $header = Gettext::getHeader($messages);
        $this->assertArrayHasKey('Plural-Forms', $header, 'Plural-Forms key ia part of header');
        // Is header removed
        $header = Gettext::deleteHeader($messages);
        $header = Gettext::getHeader($messages);
        $this->assertEquals(array(), $header, 'PoFileLoader has no header.');
        // Add header
        $messages = array();
        $expected = array('foo' => 'bar');
        Gettext::addHeader($messages, $expected);
        $actual = Gettext::getHeader($messages);
        $this->assertEquals($expected, $actual, 'PoFileLoader has a header.');
    }

    public function testLoadFullFile()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/full.po';
        $catalogue = $loader->load($resource, 'en');
        $messages = $catalogue->all('domain1');
        // File contains a Header, 2 msgid and 1 plural form and MessageBundleId
        $this->assertEquals(5, count($catalogue->all('messages')));
    }

    public function testLoadPlural()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/plural.po';
        $catalogue = $loader->load($resource, 'en');
        $messages = $catalogue->all('messages');
        $singular = $messages["index singular"];
        $all = $messages["index plural"];
        $count = count(explode("|", $all));
        // File contains a Header, 2 msgid and 1 plural form
        $this->assertEquals(6, $count);

        $singular = $messages["singular missing"];
        $all = $messages["plural missing"];
        $plurals = explode("|", $all);
        $this->assertEquals($plurals[1], '-');
        $this->assertEquals($plurals[2], '-');
        $this->assertEquals($plurals[5], '-');
        // File contains a Header, 2 msgid and 1 plural form
        $this->assertEquals(6, $count);
    }

    /**
     * Test .po context by iterate over their contexts.
     *
     * Each .po file may contain translation contexts. To load these we
     * need to iterator over the found contexts.
     */
    public function testLoadContext()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/context.po';
        $catalogue = $loader->load($resource, 'en');
        $messages = $catalogue->all('messages');

        $domains = Gettext::getContext($messages);
        $this->assertEquals(array('sheep', 'calendar'), $domains);
        Gettext::deleteContext($messages);
        $this->assertEquals(1, count($messages), 'Empty context has one message.');

        foreach( $domains as $domain) {
            $catalogue = $loader->load($resource, 'en', $domain);
            $messages = $catalogue->all($domain);
            $this->assertEquals(1, count($messages), 'Each context has one message.');
        }
    }

    /**
     * We should allow for importing POT files.
     *
     * A POT file has empty translation strings.
     * TODO: decide whether add or extend PoFileLoader with '.pot' extension
     */
    public function testLoadEmptyTranslation()
    {
        $loader = new PoFileLoader();
        $resource = __DIR__.'/../fixtures/empty-translation.po';
        $catalogue = $loader->load($resource, 'en');
        $messages = $catalogue->all('messages');

        $this->assertEquals(array('One sheep|@count sheep' => '|', 'Monday' => '', 'One sheep' => '', '@count sheep' => '|'), $messages, 'Empty translation available.');
    }
}
