<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\ProcessBuilder;

class ProcessBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldInheritEnvironmentVars()
    {
        $snapshot = $_ENV;
        $_ENV = $expected = array('foo' => 'bar');

        $proc = ProcessBuilder::create()
            ->add('foo')
            ->inheritEnvironmentVariables()
            ->getProcess()
        ;

        $this->assertNull($proc->getEnv(), '->inheritEnvironmentVariables() copies $_ENV');

        $_ENV = $snapshot;
    }

    public function testShouldInheritAndOverrideEnvironmentVars()
    {
        $snapshot = $_ENV;
        $_ENV = array('foo' => 'bar', 'bar' => 'baz');
        $expected = array('foo' => 'foo', 'bar' => 'baz');

        $proc= ProcessBuilder::create()
            ->add('foo')
            ->inheritEnvironmentVariables()
            ->setEnv('foo', 'foo')
            ->getProcess()
        ;

        $this->assertEquals($expected, $proc->getEnv(), '->inheritEnvironmentVariables() copies $_ENV');

        $_ENV = $snapshot;
    }

    public function testShouldInheritEnvironmentVarsByDefault()
    {
        $proc = ProcessBuilder::create()
            ->add('foo')
            ->getProcess()
        ;

        $this->assertNull($proc->getEnv());
    }

    public function testShouldNotReplaceExplicitlySetVars()
    {
        $snapshot = $_ENV;
        $_ENV = array('foo' => 'bar');
        $expected = array('foo' => 'baz');

        $proc = ProcessBuilder::create()
            ->setEnv('foo', 'baz')
            ->inheritEnvironmentVariables()
            ->add('foo')
            ->getProcess()
        ;

        $this->assertEquals($expected, $proc->getEnv(), '->inheritEnvironmentVariables() copies $_ENV');

        $_ENV = $snapshot;
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\InvalidArgumentException
     */
    public function testNegativeTimeoutFromSetter()
    {
        ProcessBuilder::create()->setTimeout(-1);
    }

    public function testNullTimeout()
    {
        $proc = ProcessBuilder::create()
            ->add('foo')
            ->setTimeout(10)
            ->setTimeout(0)
            ->getProcess()
        ;

        $this->assertEquals(0, $proc->getTimeout());
    }

    public function testShouldSetArguments()
    {
        $proc = ProcessBuilder::create(array('initial'))
            ->setArguments(array('second'))
            ->getProcess()
        ;

        $this->assertContains('second', $proc->getCommandLine());
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\InvalidArgumentException
     */
    public function testAddAcceptStringOnly()
    {
        ProcessBuilder::create()->add(array());
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\InvalidArgumentException
     */
    public function testSetArgumentsAcceptStringOnly()
    {
        ProcessBuilder::create()->setArguments(array(array()));
    }

    public function testAddUnescapedArguments()
    {
        $proc = ProcessBuilder::create(array('fooEscaped'))
            ->add('barEscaped')
            ->add('FooUnescaped', false)
            ->getProcess()
        ;

        $this->assertContains(
            escapeshellarg('fooEscaped') . ' ' . escapeshellarg('barEscaped') . ' FooUnescaped',
            $proc->getCommandLine()
        );
    }
}
