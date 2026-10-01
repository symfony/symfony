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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;

class KeybindingsTest extends TestCase
{
    #[DataProvider('provideValidKeyIds')]
    public function testValidKeyIdIsAccepted(string $keyId)
    {
        $keybindings = new Keybindings(['action' => [$keyId]]);

        $this->assertSame([$keyId], $keybindings->getBindings('action'));
    }

    public static function provideValidKeyIds(): iterable
    {
        yield ['c'];
        yield ['+'];
        yield ['ctrl++'];
        yield [Key::ENTER];
        yield [Key::ctrl('c')];
        yield [Key::ctrlShift('k')];
        yield ['CTRL+C'];
        yield ['shift+ctrl+alt+super+hyper+meta+a'];
    }

    #[DataProvider('provideInvalidKeyIds')]
    public function testInvalidKeyIdIsRejected(string $keyId)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Invalid key id "%s": expected a key, optionally preceded by modifiers among "shift", "ctrl", "alt", "super", "hyper", "meta".', $keyId));

        new Keybindings(['action' => ['ctrl+c', $keyId]]);
    }

    public static function provideInvalidKeyIds(): iterable
    {
        yield 'typo' => ['ctlr+x'];
        yield 'macOS name' => ['cmd+c'];
        yield 'no key' => ['ctrl+'];
        yield 'empty' => [''];
    }
}
