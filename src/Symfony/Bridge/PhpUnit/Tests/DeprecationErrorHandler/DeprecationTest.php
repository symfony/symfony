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
use Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerForV5;

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
        $deprecation = new Deprecation('💩', $this->debugBacktrace(), __FILE__);
        $this->assertTrue($deprecation->isSelf());
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
        $this->assertFalse($deprecation->isIndirect());
    }

    public function providerIsSelf(): array
    {
        return [
            'not_from_vendors_file' => [true, '', 'MyClass1', ''],
            'nonexistent_file' => [false, '', 'MyClass1', 'dummy_vendor_path'],
            'serialized_trace_without_triggering_file' => [
                true,
                serialize(['class' => '', 'method' => '', 'deprecation' => '', 'files_stack' => []]),
                SymfonyTestsListenerForV5::class,
                '',
            ],
            'serialized_trace_with_not_from_vendors_triggering_file' => [
                true,
                serialize([
                    'class' => '',
                    'method' => '',
                    'deprecation' => '',
                    'triggering_file' => '',
                    'files_stack' => [],
                ]),
                SymfonyTestsListenerForV5::class,
                '',
            ],
            'serialized_trace_with_nonexistent_triggering_file' => [
                false,
                serialize([
                    'class' => '',
                    'method' => '',
                    'deprecation' => '',
                    'triggering_file' => 'dummy_vendor_path',
                    'files_stack' => [],
                ]),
                SymfonyTestsListenerForV5::class,
                '',
            ],
        ];
    }

    /**
     * @dataProvider providerIsSelf
     */
    public function testIsSelf(bool $expectedIsSelf, string $message, string $traceClass, string $file): void
    {
        $trace = [
            ['class' => 'MyClass1', 'function' => 'myMethod'],
            ['class' => $traceClass, 'function' => 'myMethod'],
        ];
        $deprecation = new Deprecation($message, $trace, $file);
        $this->assertEquals($expectedIsSelf, $deprecation->isSelf());
    }

    /**
     * This method is here to simulate the extra level from the piece of code
     * triggering an error to the error handler.
     */
    public function debugBacktrace(): array
    {
        return debug_backtrace();
    }
}
