<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\CommandDecorator;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class CommandDecoratorTest extends \PHPUnit_Framework_TestCase
{
    private $commandDecorator;
    private $decoratedCommand;

    public function setUp()
    {
        $this->decoratedCommand = $this->getMock('Symfony\Component\Console\Command\CommandInterface');
        $this->commandDecorator = new CommandDecorator($this->decoratedCommand);
    }

    public function testSetApplication()
    {
        $application = new Application();
        $this->setDecorationExpectation('setApplication')->with($application);
        $this->commandDecorator->setApplication($application);
    }

    public function testGetHelperSet()
    {
        $helperSet = new HelperSet();
        $this->setDecorationExpectation('getHelperSet', $helperSet);
        $this->assertEquals($helperSet, $this->commandDecorator->getHelperSet());
    }

    public function testGetApplication()
    {
        $application = new Application();
        $this->setDecorationExpectation('getApplication', $application);
        $this->assertEquals($application, $this->commandDecorator->getApplication());
    }

    public function testIsEnabled()
    {
        $this->setDecorationExpectation('isEnabled', true);
        $this->assertTrue($this->commandDecorator->isEnabled());
    }

    public function testRun()
    {
        $input = new StringInput('asf');
        $output = new NullOutput();
        $this->setDecorationExpectation('run', 1)->with($input, $output);
        $this->assertEquals(1, $this->commandDecorator->run($input, $output));
    }

    public function testMergeApplicationDefinition()
    {
        $this->setDecorationExpectation('mergeApplicationDefinition')->with(true);
        $this->commandDecorator->mergeApplicationDefinition(true);
    }

    public function testGetDefinition()
    {
        $definition = new InputDefinition();
        $this->setDecorationExpectation('getDefinition', $definition);
        $this->assertEquals($definition, $this->commandDecorator->getDefinition());
    }

    public function testGetNativeDefinition()
    {
        $definition = new InputDefinition();
        $this->setDecorationExpectation('getNativeDefinition', $definition);
        $this->assertEquals($definition, $this->commandDecorator->getNativeDefinition());
    }

    public function testGetName()
    {
        $this->setDecorationExpectation('getName', 'foo:bar');
        $this->assertEquals('foo:bar', $this->commandDecorator->getName());
    }

    public function testGetDescription()
    {
        $this->setDecorationExpectation('getDescription', 'description');
        $this->assertEquals('description', $this->commandDecorator->getDescription());
    }

    public function testGetHelp()
    {
        $this->setDecorationExpectation('getHelp', 'how to');
        $this->assertEquals('how to', $this->commandDecorator->getHelp());
    }

    public function testGetProcessedHelp()
    {
        $this->setDecorationExpectation('getProcessedHelp', 'how to');
        $this->assertEquals('how to', $this->commandDecorator->getProcessedHelp());
    }

    public function testGetAliases()
    {
        $this->setDecorationExpectation('getAliases', array('foo:meh'));
        $this->assertEquals(array('foo:meh'), $this->commandDecorator->getAliases());
    }

    public function testGetSynopsis()
    {
        $this->setDecorationExpectation('getSynopsis', 'synopsis');
        $this->assertEquals('synopsis', $this->commandDecorator->getSynopsis());
    }

    private function setDecorationExpectation($method, $returnValue = null)
    {
        return $this->decoratedCommand->expects($this->once())->
            method($method)->
            will($this->returnValue($returnValue));
    }
}
