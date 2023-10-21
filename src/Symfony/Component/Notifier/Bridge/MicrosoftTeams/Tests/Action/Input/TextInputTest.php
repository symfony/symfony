<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests\Action\Input;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\TextInput;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Test\Action\Input\AbstractInputTestCase;

final class TextInputTest extends AbstractInputTestCase
{
    public function createInput(): TextInput
    {
        return new TextInput();
    }

    public function testIsMultilineWithTrue()
    {
        $input = $this->createInput()
            ->isMultiline(true);

        $this->assertTrue($input->toArray()['isMultiline']);
    }

    public function testIsMultilineWithFalse()
    {
        $input = $this->createInput()
            ->isMultiline(false);

        $this->assertFalse($input->toArray()['isMultiline']);
    }

    public function testMaxLength()
    {
        $input = $this->createInput()
            ->maxLength($value = 10);

        $this->assertSame($value, $input->toArray()['maxLength']);
    }

    public function testToArray()
    {
        $this->assertSame(
            [
                '@type' => 'TextInput',
            ],
            $this->createInput()->toArray()
        );
    }
}
