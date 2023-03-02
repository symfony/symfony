<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\Exception\InvalidArgumentException;

/**
 * @author Alexander Cheprasov <cheprasov.84@ya.ru>
 */
class ButtonBuilderTest extends TestCase
{
    public static function getValidNames()
    {
        return [
            ['reset'],
            ['submit'],
            ['foo'],
            ['0'],
            [0],
        ];
    }

    /**
     * @dataProvider getValidNames
     */
    public function testValidNames($name)
    {
        $this->assertInstanceOf(ButtonBuilder::class, new ButtonBuilder($name));
    }

    public function testNameContainingIllegalCharacters()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The name "button[]" contains illegal characters. Names should start with a letter, digit or underscore and only contain letters, digits, numbers, underscores ("_"), hyphens ("-") and colons (":").');

        $this->assertInstanceOf(ButtonBuilder::class, new ButtonBuilder('button[]'));
    }

    public static function getInvalidNames()
    {
        return [
            [''],
            [false],
            [null],
        ];
    }

    /**
     * @dataProvider getInvalidNames
     */
    public function testInvalidNames($name)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Buttons cannot have empty names.');
        new ButtonBuilder($name);
    }
}
