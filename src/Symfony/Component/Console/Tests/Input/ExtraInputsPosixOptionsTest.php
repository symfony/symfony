<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Tom Klingenberg <https://github.com/ktomk/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Input;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Extra test-case to cover the introduction of $flags for InputInterface::hasParameterOption()
 * and InputInterface::getParameterOption().
 */
class ExtraInputsPosixOptionsTest extends TestCase
{
    public function testArrayInput()
    {
        $input = new ArrayInput(array('name' => 'Fabien', '--foo' => 'bar', '--', '--bar', '--baz' => 'foo'));
        $this->hasParameterAssertions($input);
        $this->getParameterAssertions($input);
    }

    public function testArgvInput()
    {
        $input = new ArgvInput(array('Fabien', '--foo', 'bar', '--', '--bar', '--baz', 'foo'));
        $this->hasParameterAssertions($input);
        $this->getParameterAssertions($input);
    }

    private function hasParameterAssertions(InputInterface $input)
    {
        $this->assertInputHasParameterOption($input, '--foo', '--foo always exists');
        $this->assertInputNeverHasParameterOption($input, '--zzz', '--zzz never exists');
        $this->assertInputHasParameterOption(
            $input,
            array('--zzz', '--foo'),
            '--foo is found with --zzz that never exists'
        );
        $this->assertInputHasOnlyNotParameterOption($input, '--bar', '--bar is not found');
        $this->assertInputHasParameterOption($input, array('--bar', '--foo'), '--foo is found even --bar is not found');
        $this->assertInputHasOnlyNotParameterOption($input, '--baz');

        $this->assertTrue($input->hasParameterOption('--bar'), 'Default behaviour');
        $this->assertFalse($input->hasParameterOption('--bar', $input::OPTION_FLAG_POSIX), 'Posix flag');

        $this->assertFalse($input->hasParameterOption(array('--bar'), $input::OPTION_FLAG_POSIX), 'Posix flag');
        $this->assertFalse(
            $input->hasParameterOption(array('--bar', '--baz'), $input::OPTION_FLAG_POSIX),
            'Posix flag'
        );
        $this->assertTrue(
            $input->hasParameterOption(array('--bar', '--baz', '--foo'), $input::OPTION_FLAG_POSIX),
            'Posix flag'
        );
    }

    /**
     * Assert that regardless of flags, input never has an option.
     *
     * @param InputInterface $input
     * @param $values
     * @param null $message
     */
    private function assertInputNeverHasParameterOption(InputInterface $input, $values, $message = null)
    {
        if (strlen($message)) {
            $message = " ($message)";
        }

        $this->assertFalse($input->hasParameterOption($values), "default flag $message");
        $this->assertFalse($input->hasParameterOption($values, $input::OPTION_FLAG_POSIX), "posix flag $message");
    }

    /**
     * Assert that regardless of the flag the values are available.
     *
     * @param InputInterface $input
     * @param $values
     * @param null $message
     */
    private function assertInputHasParameterOption(InputInterface $input, $values, $message = null)
    {
        if (strlen($message)) {
            $message = " ($message)";
        }

        $this->assertTrue($input->hasParameterOption($values), "default flag $message");
        $this->assertTrue($input->hasParameterOption($values, $input::OPTION_FLAG_POSIX), "posix flag $message");
    }

    /**
     * Assert that with POSIX flag the values are not available, but are available by default.
     *
     * @param InputInterface $input
     * @param $values
     * @param null $message
     */
    private function assertInputHasOnlyNotParameterOption(InputInterface $input, $values, $message = null)
    {
        if (strlen($message)) {
            $message = " ($message)";
        }

        $this->assertTrue($input->hasParameterOption($values), "default flag $message");
        $this->assertFalse($input->hasParameterOption($values, $input::OPTION_FLAG_POSIX), "posix flag $message");
    }

    /**
     * @param $input
     */
    private function getParameterAssertions(InputInterface $input)
    {
        $this->assertEquals('bar', $input->getParameterOption('--foo'));
        $this->assertSame('bar', $input->getParameterOption('--foo', false, $input::OPTION_FLAG_POSIX));

        $this->assertEquals('foo', $input->getParameterOption('--baz'));
        $this->assertSame(false, $input->getParameterOption('--baz', false, $input::OPTION_FLAG_POSIX));
    }
}
