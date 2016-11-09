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
    public function testInheritEnvironmentVars()
    {
        $_ENV['MY_VAR_1'] = 'foo';

        $proc = ProcessBuilder::create()
            ->add('foo')
            ->getProcess();

        unset($_ENV['MY_VAR_1']);

        $env = $proc->getEnv();
        $this->assertArrayHasKey('MY_VAR_1', $env);
        $this->assertEquals('foo', $env['MY_VAR_1']);
    }

    public function testProcessShouldInheritAndOverrideEnvironmentVars()
    {
        $_ENV['MY_VAR_1'] = 'foo';

        $proc = ProcessBuilder::create()
            ->setEnv('MY_VAR_1', 'bar')
            ->add('foo')
            ->getProcess();

        unset($_ENV['MY_VAR_1']);

        $env = $proc->getEnv();
        $this->assertArrayHasKey('MY_VAR_1', $env);
        $this->assertEquals('bar', $env['MY_VAR_1']);
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\InvalidArgumentException
     */
    public function testNegativeTimeoutFromSetter()
    {
        $pb = new ProcessBuilder();
        $pb->setTimeout(-1);
    }

    public function testNullTimeout()
    {
        $pb = new ProcessBuilder();
        $pb->setTimeout(10);
        $pb->setTimeout(null);

        $r = new \ReflectionObject($pb);
        $p = $r->getProperty('timeout');
        $p->setAccessible(true);

        $this->assertNull($p->getValue($pb));
    }

    public function testShouldSetArguments()
    {
        $pb = new ProcessBuilder(array('initial'));
        $pb->setArguments(array('second'));

        $proc = $pb->getProcess();

        $this->assertContains('second', $proc->getCommandLine());
    }

    public function testPrefixIsPrependedToAllGeneratedProcess()
    {
        $pb = new ProcessBuilder();
        $pb->setPrefix('/usr/bin/php');

        $proc = $pb->setArguments(array('-v'))->getProcess();
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php" "-v"', $proc->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php' '-v'", $proc->getCommandLine());
        }

        $proc = $pb->setArguments(array('-i'))->getProcess();
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php" "-i"', $proc->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php' '-i'", $proc->getCommandLine());
        }
    }

    public function testShouldEscapeArguments()
    {
        $pb = new ProcessBuilder(array('%path%', 'foo " bar', '%baz%baz'));
        $proc = $pb->getProcess();

        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->assertSame('^%"path"^% "foo \\" bar" "%baz%baz"', $proc->getCommandLine());
        } else {
            $this->assertSame("'%path%' 'foo \" bar' '%baz%baz'", $proc->getCommandLine());
        }
    }

    public function testShouldEscapeArgumentsAndPrefix()
    {
        $pb = new ProcessBuilder(array('arg'));
        $pb->setPrefix('%prefix%');
        $proc = $pb->getProcess();

        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->assertSame('^%"prefix"^% "arg"', $proc->getCommandLine());
        } else {
            $this->assertSame("'%prefix%' 'arg'", $proc->getCommandLine());
        }
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\LogicException
     */
    public function testShouldThrowALogicExceptionIfNoPrefixAndNoArgument()
    {
        ProcessBuilder::create()->getProcess();
    }

    public function testShouldNotThrowALogicExceptionIfNoArgument()
    {
        $process = ProcessBuilder::create()
            ->setPrefix('/usr/bin/php')
            ->getProcess();

        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php"', $process->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php'", $process->getCommandLine());
        }
    }

    public function testShouldNotThrowALogicExceptionIfNoPrefix()
    {
        $process = ProcessBuilder::create(array('/usr/bin/php'))
            ->getProcess();

        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->assertEquals('"/usr/bin/php"', $process->getCommandLine());
        } else {
            $this->assertEquals("'/usr/bin/php'", $process->getCommandLine());
        }
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\InvalidArgumentException
     * @expectedExceptionMessage Symfony\Component\Process\ProcessBuilder::setInput only accepts strings.
     */
    public function testInvalidInput()
    {
        $builder = ProcessBuilder::create();
        $builder->setInput(array());
    }
}
