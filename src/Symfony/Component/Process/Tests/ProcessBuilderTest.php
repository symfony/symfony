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
    /**
     * @test
     */
    public function shouldInheritEnvironmentVars()
    {
        $snapshot = $_ENV;
        $_ENV = $expected = array('foo' => 'bar');

        $pb = new ProcessBuilder();
        $pb->add('foo')->inheritEnvironmentVariables();
        $proc = $pb->getProcess();

        $this->assertNull($proc->getEnv(), '->inheritEnvironmentVariables() copies $_ENV');

        $_ENV = $snapshot;
    }

    /**
     * @test
     */
    public function shouldInheritAndOverrideEnvironmentVars()
    {
        $snapshot = $_ENV;
        $_ENV = array('foo' => 'bar', 'bar' => 'baz');
        $expected = array('foo' => 'foo', 'bar' => 'baz');

        $pb = new ProcessBuilder();
        $pb->add('foo')->inheritEnvironmentVariables()
            ->setEnv('foo', 'foo');
        $proc = $pb->getProcess();

        $this->assertEquals($expected, $proc->getEnv(), '->inheritEnvironmentVariables() copies $_ENV');

        $_ENV = $snapshot;
    }

    /**
     * @test
     */
    public function shouldInheritEnvironmentVarsByDefault()
    {
        $pb = new ProcessBuilder();
        $proc = $pb->add('foo')->getProcess();

        $this->assertNull($proc->getEnv());
    }

    /**
     * @test
     */
    public function shouldNotReplaceExplicitlySetVars()
    {
        $snapshot = $_ENV;
        $_ENV = array('foo' => 'bar');
        $expected = array('foo' => 'baz');

        $pb = new ProcessBuilder();
        $pb
            ->setEnv('foo', 'baz')
            ->inheritEnvironmentVariables()
            ->add('foo')
        ;
        $proc = $pb->getProcess();

        $this->assertEquals($expected, $proc->getEnv(), '->inheritEnvironmentVariables() copies $_ENV');

        $_ENV = $snapshot;
    }

    /**
     * @expectedException \InvalidArgumentException
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
}
