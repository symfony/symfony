<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Deprecation;

class DeprecationTest extends TestCase
{
    public function testItCanDetermineTheClassWhereTheDeprecationHappened()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertTrue($deprecation->originatesFromAnObject());
        $this->assertSame(self::class, $deprecation->originatingClass());
        $this->assertSame(__FUNCTION__, $deprecation->originatingMethod());
    }

    public function testItCanTellWhetherItIsInternal()
    {
        $r = new \ReflectionClass(Deprecation::class);

        if (dirname($r->getFileName(), 2) !== dirname(__DIR__, 2)) {
            $this->markTestSkipped('Test case is not compatible with having the bridge in vendor/');
        }

        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertSame(Deprecation::TYPE_SELF, $deprecation->getType());
    }

    public function testLegacyTestMethodIsDetectedAsSuch()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertTrue($deprecation->isLegacy('whatever'));
    }

    public function testItCanBeConvertedToAString()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertContains('💩', $deprecation->toString());
        $this->assertContains(__FUNCTION__, $deprecation->toString());
    }

    public function testItRulesOutFilesOutsideVendorsAsIndirect()
    {
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertNotSame(Deprecation::TYPE_INDIRECT, $deprecation->getType());
    }

    /**
     * This method is here to simulate the extra level from the piece of code
     * triggering an error to the error handler
     */
    public function debugBacktrace(): array
    {
        return debug_backtrace();
    }
}
