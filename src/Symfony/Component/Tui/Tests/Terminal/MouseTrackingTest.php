<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Terminal;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

class MouseTrackingTest extends TestCase
{
    private const ENABLE = "\x1b[?1000h\x1b[?1002h\x1b[?1006h";
    private const DISABLE = "\x1b[?1006l\x1b[?1002l\x1b[?1000l";

    public function testDisabledByDefault()
    {
        $terminal = new VirtualTerminal();
        $terminal->start(static function () {}, static function () {}, static function () {});

        $this->assertStringNotContainsString(self::ENABLE, $terminal->getOutput());
    }

    public function testEnableAfterStartEmitsSequence()
    {
        $terminal = new VirtualTerminal();
        $terminal->start(static function () {}, static function () {}, static function () {});
        $terminal->clearOutput();

        $terminal->enableMouseTracking();

        $this->assertSame(self::ENABLE, $terminal->getOutput());
    }

    public function testEnableBeforeStartIsEmittedOnStart()
    {
        $terminal = new VirtualTerminal();
        $terminal->enableMouseTracking();

        $terminal->start(static function () {}, static function () {}, static function () {});

        $this->assertStringContainsString(self::ENABLE, $terminal->getOutput());
    }

    public function testDisableEmitsSequence()
    {
        $terminal = new VirtualTerminal();
        $terminal->start(static function () {}, static function () {}, static function () {});
        $terminal->enableMouseTracking();
        $terminal->clearOutput();

        $terminal->disableMouseTracking();

        $this->assertSame(self::DISABLE, $terminal->getOutput());
    }

    public function testEnableIsIdempotent()
    {
        $terminal = new VirtualTerminal();
        $terminal->start(static function () {}, static function () {}, static function () {});
        $terminal->enableMouseTracking();
        $terminal->clearOutput();

        $terminal->enableMouseTracking();

        $this->assertSame('', $terminal->getOutput());
    }
}
