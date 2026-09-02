<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Input\Key;

class KeyTest extends TestCase
{
    #[DataProvider('provideLabels')]
    public function testLabel(string $expected, string $key)
    {
        $this->assertSame($expected, Key::label($key));
    }

    public static function provideLabels(): iterable
    {
        yield ['Esc', Key::ESCAPE];
        yield ['↵', Key::ENTER];
        yield ['▲', Key::UP];
        yield ['⇟', Key::PAGE_DOWN];
        yield ['F6', Key::F6];
        yield ['Ctrl+C', Key::ctrl('c')];
        yield ['Ctrl+Shift+X', Key::ctrlShift('x')];
        yield ['Ctrl+Space', Key::ctrl(Key::SPACE)];
        yield ['Alt+⇞', Key::alt(Key::PAGE_UP)];
        yield ['?', '?'];
    }
}
