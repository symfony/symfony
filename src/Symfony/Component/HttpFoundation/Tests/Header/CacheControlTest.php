<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Header;

use Symfony\Component\HttpFoundation\Header\CacheControl;

class CacheControlTest extends \PHPUnit_Framework_TestCase
{

    public function testGetHeader()
    {
        $cacheControl = new CacheControl();
        $cacheControl->addDirective('public', '#a');
        $this->assertTrue($cacheControl->hasDirective('public'));
        $this->assertEquals('#a', $cacheControl->getDirective('public'));
    }

    public function testDirectiveAccessors()
    {
        $cacheControl = new CacheControl();
        $cacheControl->addDirective('public');

        $this->assertTrue($cacheControl->hasDirective('public'));
        $this->assertTrue($cacheControl->getDirective('public'));
        $this->assertEquals('public', $cacheControl);

        $cacheControl->addDirective('max-age', 10);
        $this->assertTrue($cacheControl->hasDirective('max-age'));
        $this->assertEquals(10, $cacheControl->getDirective('max-age'));
        $this->assertEquals('max-age=10, public', $cacheControl);

        $cacheControl->removeDirective('max-age');
        $this->assertFalse($cacheControl->hasDirective('max-age'));
    }

    public function testDirectiveParsing()
    {
        $cacheControl = CacheControl::fromString('public, max-age=10');
        $this->assertTrue($cacheControl->hasDirective('public'));
        $this->assertTrue($cacheControl->getDirective('public'));

        $this->assertTrue($cacheControl->hasDirective('max-age'));
        $this->assertEquals(10, $cacheControl->getDirective('max-age'));

        $cacheControl->addDirective('s-maxage', 100);
        $this->assertEquals('max-age=10, public, s-maxage=100', $cacheControl);
    }

    public function testDirectiveParsingQuotedZero()
    {
        $cacheControl = CacheControl::fromString('max-age="0"');
        $this->assertTrue($cacheControl->hasDirective('max-age'));
        $this->assertEquals(0, $cacheControl->getDirective('max-age'));
    }
}
