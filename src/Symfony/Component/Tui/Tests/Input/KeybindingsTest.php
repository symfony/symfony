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
        yield ['esc'];
        yield ['return'];
        yield ['clear'];
        yield ['alt+7'];
        yield ['ctrl+]'];
        yield ['F12'];
    }

    public function testEveryKeyConstantIsAccepted()
    {
        $keyIds = array_values((new \ReflectionClass(Key::class))->getConstants(\ReflectionClassConstant::IS_PUBLIC));
        $keybindings = new Keybindings(['action' => $keyIds]);

        $this->assertSame($keyIds, $keybindings->getBindings('action'));
    }

    #[DataProvider('provideUnknownKeyNames')]
    public function testUnknownKeyNameIsRejected(string $keyId, string $key)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Invalid key id "%s": "%s" is not a key name', $keyId, $key));

        new Keybindings(['action' => ['ctrl+c', $keyId]]);
    }

    public static function provideUnknownKeyNames(): iterable
    {
        yield 'typo' => ['escpe', 'escpe'];
        yield 'typo behind a modifier' => ['ctrl+escpe', 'escpe'];
        yield 'missing underscore' => ['pageup', 'pageup'];
        yield 'no such function key' => ['f13', 'f13'];
        yield 'keypad enter' => ['kp_enter', 'kp_enter'];
        yield 'non-ASCII character' => ['alt+é', 'é'];
        yield 'space character' => ['ctrl+ ', ' '];
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
