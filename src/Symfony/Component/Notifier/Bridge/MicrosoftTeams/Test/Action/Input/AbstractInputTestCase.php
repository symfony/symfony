<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Test\Action\Input;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\Action\Input\AbstractInput;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
abstract class AbstractInputTestCase extends TestCase
{
    abstract public function createInput(): AbstractInput;

    public function testId()
    {
        $input = $this->createInput();

        $input->id($value = '1234');

        $this->assertSame($value, $input->toArray()['id']);
    }

    public function testIsRequiredWithFalse()
    {
        $input = $this->createInput();

        $input->isRequired(false);

        $this->assertFalse($input->toArray()['isRequired']);
    }

    public function testIsRequiredWithTrue()
    {
        $input = $this->createInput();

        $input->isRequired(true);

        $this->assertTrue($input->toArray()['isRequired']);
    }

    public function testTitle()
    {
        $input = $this->createInput();

        $input->title($value = 'Hey Symfony!');

        $this->assertSame($value, $input->toArray()['title']);
    }

    public function testValue()
    {
        $input = $this->createInput();

        $input->value($value = 'Community power!');

        $this->assertSame($value, $input->toArray()['value']);
    }
}
