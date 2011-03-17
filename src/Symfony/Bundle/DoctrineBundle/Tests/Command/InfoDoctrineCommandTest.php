<?php

namespace Symfony\Bundle\DoctrineBundle\Tests\Command;

use Symfony\Bundle\DoctrineBundle\Tests\TestCase;
use Symfony\Bundle\DoctrineBundle\Command\InfoDoctrineCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;

require_once __DIR__.'/../DependencyInjection/Fixtures/Bundles/YamlBundle/Entity/Test.php';

class InfoDoctrineCommandTest extends TestCase
{
    public function testAnnotationsBundle()
    {
        $input = new StringInput("doctrine:mapping:info");
        $output = $this->getMock('Symfony\Component\Console\Output\OutputInterface');
        $output->expects($this->at(0))
               ->method('write')
               ->with($this->equalTo("Found <info>1</info> entities mapped in entity manager <info>default</info>:\n"), $this->equalTo(true));
        $output->expects($this->at(1))
               ->method('write')
               ->with($this->equalTo("<info>[OK]</info>   Fixtures\Bundles\YamlBundle\Entity\Test"), $this->equalTo(true));

        $testContainer = $this->createYamlBundleTestContainer();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\Kernel', array(), array(), '', false);
        $kernel->expects($this->once())
               ->method('getBundles')
               ->will($this->returnValue(array()));
        $kernel->expects($this->once())
               ->method('getContainer')
               ->will($this->returnValue($testContainer));
        $application = new Application($kernel);

        $cmd = new InfoDoctrineCommand();
        $cmd->setApplication($application);
        $cmd->run($input, $output);
    }
}