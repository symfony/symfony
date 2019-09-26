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
    public function getValidNames()
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
        $this->assertInstanceOf('\Symfony\Component\Form\ButtonBuilder', new ButtonBuilder($name));
    }

    public function testNameContainingIllegalCharacters()
    {
        $this->expectException('Symfony\Component\Form\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('The name "button[]" contains illegal characters. Names should start with a letter, digit or underscore and only contain letters, digits, numbers, underscores ("_"), hyphens ("-") and colons (":").');

        $this->assertInstanceOf('\Symfony\Component\Form\ButtonBuilder', new ButtonBuilder('button[]'));
    }

    /**
     * @group legacy
     */
    public function testNameStartingWithIllegalCharacters()
    {
        $this->assertInstanceOf('\Symfony\Component\Form\ButtonBuilder', new ButtonBuilder('Button'));
    }

    public function getInvalidNames()
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
