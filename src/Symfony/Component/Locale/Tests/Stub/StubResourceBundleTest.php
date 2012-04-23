<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\Locale\Tests\Stub;

use Symfony\Component\Locale\Stub\StubResourceBundle;

class StubResourceBundleTest extends \PHPUnit_Framework_TestCase
{
    public function testWithValidLocale()
    {
        $bundle = new StubResourceBundle('es', __DIR__.'/../fixtures/resourcebundle');

        $this->assertEquals(2, $bundle->get('testint'));
        $this->assertEquals('Hola Mundo!', $bundle->get('teststring'));
        $this->assertEquals(pack('H*', 'a1b2c3d4e5f67890'), $bundle->get('testbin'));
        $this->assertEquals(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 0), $bundle->get('testvector'));
        $this->assertTrue($bundle->get('testarray') instanceof StubResourceBundle);
        $this->assertTrue($bundle->get('testtable') instanceof StubResourceBundle);
        $this->assertEquals(3, $bundle->get('testtable')->get('major'));
        $this->assertEquals(3, $bundle->get('testtable')->get(0));
        $this->assertEquals('cadena 1', $bundle->get('testarray')->get(0));

        // ArrayAccess
        $this->assertEquals($bundle->get('testarray'), $bundle['testarray']);
        $this->assertEquals($bundle->get('testtable')->get('major'), $bundle['testtable']['major']);
    }

    public function testGetLocalesValidBundle()
    {
        $locales = StubResourceBundle::getLocales( __DIR__.'/../fixtures/resourcebundle');
        $this->assertEquals(array('es', 'root'), $locales);
    }

    public function testGetLocalesInvalidBundle()
    {
        $locales = StubResourceBundle::getLocales( __DIR__.'/../fixtures');
        $this->assertFalse($locales);
    }

    public function testCountValidBundle()
    {
        $bundle = new StubResourceBundle('es', __DIR__.'/../fixtures/resourcebundle');
        $this->assertEquals(6, count($bundle));
        $this->assertEquals(3, count($bundle->get('testarray')));
        $this->assertEquals(3, count($bundle->get('testtable')));
        $this->assertEquals(6, $bundle->count());
        $this->assertEquals(3, $bundle->get('testarray')->count());
        $this->assertEquals(3, $bundle->get('testtable')->count());
    }
}