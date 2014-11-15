<?php

namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\CommandLine;

class CommandLineTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideVariousCommandsAndParameters
     */
    public function testCommandLinePrepare($commandLine, $placeholder, $parameters, $expected)
    {
        $commandLine = new CommandLine($commandLine, $placeholder);
        $this->assertSame($expected, $commandLine->prepare($parameters));
    }

    public function provideVariousCommandsAndParameters()
    {
        return array(
            array("{} | grep {}", CommandLine::DEFAULT_PLACEHOLDER, array('/usr/bin/ls', 'symfony'), "'/usr/bin/ls' | grep 'symfony'"),
            array("## | grep ##", '##', array('/usr/bin/ls', 'symfony'), "'/usr/bin/ls' | grep 'symfony'"),
            array("{ } | grep {}", CommandLine::DEFAULT_PLACEHOLDER, array('symfony'), "{ } | grep 'symfony'"),
            array("exec {} | grep {} > symfony.log", CommandLine::DEFAULT_PLACEHOLDER, array('/usr/bin/ls', 'symfony'), "exec '/usr/bin/ls' | grep 'symfony' > symfony.log"),
            array("exec ## | grep ## > symfony.log", '##', array('/usr/bin/ls', 'symfony'), "exec '/usr/bin/ls' | grep 'symfony' > symfony.log"),
            array("exec Ê™ | grep Ê™ > symfony.log", 'Ê™', array('/usr/bin/ls', 'symfony'), "exec '/usr/bin/ls' | grep 'symfony' > symfony.log"),
            array("exec {} | grep {} > symfony.log", CommandLine::DEFAULT_PLACEHOLDER, array('i\'m', 'symfony'), "exec 'i'\\''m' | grep 'symfony' > symfony.log"),
            array("{} | grep {}", CommandLine::DEFAULT_PLACEHOLDER, array('/usr/bin/ls', 'symfony'), "'/usr/bin/ls' | grep 'symfony'"),
        );
    }
}
