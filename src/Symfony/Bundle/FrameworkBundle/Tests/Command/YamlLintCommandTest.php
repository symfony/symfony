<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\YamlLintCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Tests the YamlLintCommand.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class YamlLintCommandTest extends TestCase
{
    private $files;

    public function testLintCorrectFile()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('foo: bar');

        $tester->execute(
            array('filename' => $filename),
            array('verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false)
        );

        $this->assertEquals(0, $tester->getStatusCode(), 'Returns 0 in case of success');
        $this->assertContains('OK', trim($tester->getDisplay()));
    }

    public function testLintIncorrectFile()
    {
        $incorrectContent = '
foo:
bar';
        $tester = $this->createCommandTester();
        $filename = $this->createFile($incorrectContent);

        $tester->execute(array('filename' => $filename), array('decorated' => false));

        $this->assertEquals(1, $tester->getStatusCode(), 'Returns 1 in case of error');
        $this->assertContains('Unable to parse at line 3 (near "bar").', trim($tester->getDisplay()));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLintFileNotReadable()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('');
        unlink($filename);

        $tester->execute(array('filename' => $filename), array('decorated' => false));
    }

    public function testGetHelp()
    {
        $command = new YamlLintCommand();
        $expected = <<<EOF
The <info>%command.name%</info> command lints a YAML file and outputs to STDOUT
the first encountered syntax error.

You can validates YAML contents passed from STDIN:

  <info>cat filename | php %command.full_name%</info>

You can also validate the syntax of a file:

  <info>php %command.full_name% filename</info>

Or of a whole directory:

  <info>php %command.full_name% dirname</info>
  <info>php %command.full_name% dirname --format=json</info>

Or find all files in a bundle:

  <info>php %command.full_name% @AcmeDemoBundle</info>

EOF;

        $this->assertEquals($expected, $command->getHelp());
    }

    public function testLintFilesFromBundleDirectory()
    {
        $tester = $this->createCommandTester($this->getKernelAwareApplicationMock());
        $tester->execute(
            array('filename' => '@AppBundle/Resources'),
            array('verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false)
        );

        $this->assertEquals(0, $tester->getStatusCode(), 'Returns 0 in case of success');
        $this->assertContains('[OK] All 0 YAML files contain valid syntax', trim($tester->getDisplay()));
    }

    /**
     * @return string Path to the new file
     */
    private function createFile($content)
    {
        $filename = tempnam(sys_get_temp_dir().'/yml-lint-test', 'sf-');
        file_put_contents($filename, $content);

        $this->files[] = $filename;

        return $filename;
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester($application = null)
    {
        if (!$application) {
            $application = new BaseApplication();
            $application->add(new YamlLintCommand());
        }

        $command = $application->find('lint:yaml');

        if ($application) {
            $command->setApplication($application);
        }

        return new CommandTester($command);
    }

    private function getKernelAwareApplicationMock()
    {
        $kernel = $this->getMockBuilder(KernelInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $kernel
            ->expects($this->once())
            ->method('locateResource')
            ->with('@AppBundle/Resources')
            ->willReturn(sys_get_temp_dir().'/yml-lint-test');

        $application = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->getMock();

        $application
            ->expects($this->once())
            ->method('getKernel')
            ->willReturn($kernel);

        $application
            ->expects($this->once())
            ->method('getHelperSet')
            ->willReturn(new HelperSet());

        $application
            ->expects($this->any())
            ->method('getDefinition')
            ->willReturn(new InputDefinition());

        $application
            ->expects($this->once())
            ->method('find')
            ->with('lint:yaml')
            ->willReturn(new YamlLintCommand());

        return $application;
    }

    protected function setUp()
    {
        @mkdir(sys_get_temp_dir().'/yml-lint-test');
        $this->files = array();
    }

    protected function tearDown()
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        rmdir(sys_get_temp_dir().'/yml-lint-test');
    }
}
