<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\Command;

use Symfony\Bundle\AsseticBundle\Command\DumpCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;

class DumpCommandTest extends \PHPUnit_Framework_TestCase
{
    private $writeTo;
    private $application;
    private $definition;
    private $kernel;
    private $container;
    private $am;

    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }

        $this->writeTo = sys_get_temp_dir().'/assetic_dump';

        $this->application = $this->getMockBuilder('Symfony\\Bundle\\FrameworkBundle\\Console\\Application')
            ->disableOriginalConstructor()
            ->getMock();
        $this->definition = $this->getMockBuilder('Symfony\\Component\\Console\\Input\\InputDefinition')
            ->disableOriginalConstructor()
            ->getMock();
        $this->kernel = $this->getMock('Symfony\\Component\\HttpKernel\\KernelInterface');
        $this->container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface');
        $this->am = $this->getMockBuilder('Assetic\\Factory\\LazyAssetManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->application->expects($this->any())
            ->method('getDefinition')
            ->will($this->returnValue($this->definition));
        $this->definition->expects($this->any())
            ->method('getArguments')
            ->will($this->returnValue(array()));
        $this->definition->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue(array(
                new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', 'dev'),
                new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switches off debug mode.'),
            )));
        $this->application->expects($this->any())
            ->method('getKernel')
            ->will($this->returnValue($this->kernel));
        $this->kernel->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($this->container));
        $this->container->expects($this->any())
            ->method('getParameter')
            ->with('assetic.write_to')
            ->will($this->returnValue($this->writeTo));
        $this->container->expects($this->once())
            ->method('get')
            ->with('assetic.asset_manager')
            ->will($this->returnValue($this->am));

        $this->command = new DumpCommand();
        $this->command->setApplication($this->application);
    }

    protected function tearDown()
    {
        if (is_dir($this->writeTo)) {
            array_map('unlink', glob($this->writeTo.'/*'));
            rmdir($this->writeTo);
        }
    }

    public function testEmptyAssetManager()
    {
        $this->am->expects($this->once())
            ->method('getNames')
            ->will($this->returnValue(array()));

        $this->command->run(new ArrayInput(array()), new NullOutput());
    }

    public function testDumpOne()
    {
        $asset = $this->getMock('Assetic\\Asset\\AssetInterface');

        $this->am->expects($this->once())
            ->method('getNames')
            ->will($this->returnValue(array('test_asset')));
        $this->am->expects($this->once())
            ->method('get')
            ->with('test_asset')
            ->will($this->returnValue($asset));
        $this->am->expects($this->once())
            ->method('getFormula')
            ->with('test_asset')
            ->will($this->returnValue(array()));
        $this->am->expects($this->once())
            ->method('isDebug')
            ->will($this->returnValue(false));
        $asset->expects($this->once())
            ->method('getTargetPath')
            ->will($this->returnValue('test_asset.css'));
        $asset->expects($this->once())
            ->method('dump')
            ->will($this->returnValue('/* test_asset */'));

        $this->command->run(new ArrayInput(array()), new NullOutput());

        $this->assertFileExists($this->writeTo.'/test_asset.css');
        $this->assertEquals('/* test_asset */', file_get_contents($this->writeTo.'/test_asset.css'));
    }

    public function testDumpDebug()
    {
        $asset = $this->getMock('Assetic\\Asset\\AssetCollection');
        $leaf = $this->getMock('Assetic\\Asset\\AssetInterface');

        $this->am->expects($this->once())
            ->method('getNames')
            ->will($this->returnValue(array('test_asset')));
        $this->am->expects($this->once())
            ->method('get')
            ->with('test_asset')
            ->will($this->returnValue($asset));
        $this->am->expects($this->once())
            ->method('getFormula')
            ->with('test_asset')
            ->will($this->returnValue(array()));
        $this->am->expects($this->once())
            ->method('isDebug')
            ->will($this->returnValue(true));
        $asset->expects($this->once())
            ->method('getTargetPath')
            ->will($this->returnValue('test_asset.css'));
        $asset->expects($this->once())
            ->method('dump')
            ->will($this->returnValue('/* test_asset */'));
        $asset->expects($this->once())
            ->method('getIterator')
            ->will($this->returnValue(new \ArrayIterator(array($leaf))));
        $leaf->expects($this->once())
            ->method('getTargetPath')
            ->will($this->returnValue('test_leaf.css'));
        $leaf->expects($this->once())
            ->method('dump')
            ->will($this->returnValue('/* test_leaf */'));

        $this->command->run(new ArrayInput(array()), new NullOutput());

        $this->assertFileExists($this->writeTo.'/test_asset.css');
        $this->assertFileExists($this->writeTo.'/test_leaf.css');
        $this->assertEquals('/* test_asset */', file_get_contents($this->writeTo.'/test_asset.css'));
        $this->assertEquals('/* test_leaf */', file_get_contents($this->writeTo.'/test_leaf.css'));
    }
}
