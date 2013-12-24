<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Command;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;
use Symfony\Bridge\Twig\Command\LintCommand;

/**
 * @covers \Symfony\Bridge\Twig\Command\LintCommand
 */
class LintCommandTest extends \PHPUnit_Framework_TestCase
{
    private $files;

    public function testLintCorrectFile()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('{{ foo }}');

        $ret = $tester->execute(array('filename' => $filename));

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertRegExp('/^OK in /', $tester->getDisplay());
    }

    public function testLintIncorrectFile()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('{{ foo');

        $ret = $tester->execute(array('filename' => $filename));

        $this->assertEquals(1, $ret, 'Returns 1 in case of error');
        $this->assertRegExp('/^KO in /', $tester->getDisplay());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLintFileNotReadable()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('');
        unlink($filename);

        $ret = $tester->execute(array('filename' => $filename));
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester()
    {
        $twig = new \Twig_Environment(new \Twig_Loader_Filesystem());

        $application = new Application();
        $application->add(new LintCommand($twig));
        $command = $application->find('twig:lint');

        return new CommandTester($command);
    }

    /**
     * @return string Path to the new file
     */
    private function createFile($content)
    {
        $filename = tempnam(sys_get_temp_dir(), 'sf-');
        file_put_contents($filename, $content);

        $this->files[] = $filename;

        return $filename;
    }

    public function setUp()
    {
        $this->files = array();
    }

    public function tearDown()
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
