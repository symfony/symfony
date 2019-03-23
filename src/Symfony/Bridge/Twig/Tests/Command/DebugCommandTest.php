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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Command\DebugCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class DebugCommandTest extends TestCase
{
    public function testDebugCommand()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([], ['decorated' => false]);

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains('Functions', trim($tester->getDisplay()));
    }

    public function testLineSeparatorInLoaderPaths()
    {
        // these paths aren't realistic,
        // they're configured to force the line separator
        $tester = $this->createCommandTester([
            'Acme' => ['extractor', 'extractor'],
            '!Acme' => ['extractor', 'extractor'],
            FilesystemLoader::MAIN_NAMESPACE => ['extractor', 'extractor'],
        ]);
        $ret = $tester->execute([], ['decorated' => false]);
        $ds = \DIRECTORY_SEPARATOR;
        $loaderPaths = <<<TXT
Loader Paths
------------

 ----------- ------------ 
  Namespace   Paths       
 ----------- ------------ 
  @Acme       extractor$ds  
              extractor$ds  
                          
  @!Acme      extractor$ds  
              extractor$ds  
                          
  (None)      extractor$ds  
              extractor$ds  
 ----------- ------------
TXT;

        $this->assertEquals(0, $ret, 'Returns 0 in case of success');
        $this->assertContains($loaderPaths, trim($tester->getDisplay(true)));
    }

    public function testWithGlobals()
    {
        $message = '<error>foo</error>';
        $tester = $this->createCommandTester([], ['message' => $message]);
        $tester->execute([], ['decorated' => true]);

        $display = $tester->getDisplay();

        $this->assertContains(\json_encode($message), $display);
    }

    public function testWithGlobalsJson()
    {
        $globals = ['message' => '<error>foo</error>'];

        $tester = $this->createCommandTester([], $globals);
        $tester->execute(['--format' => 'json'], ['decorated' => true]);

        $display = $tester->getDisplay();
        $display = \json_decode($display, true);

        $this->assertSame($globals, $display['globals']);
    }

    public function testWithFilter()
    {
        $tester = $this->createCommandTester([]);
        $tester->execute(['--format' => 'json'], ['decorated' => false]);
        $display = $tester->getDisplay();
        $display1 = \json_decode($display, true);

        $tester->execute(['filter' => 'date', '--format' => 'json'], ['decorated' => false]);
        $display = $tester->getDisplay();
        $display2 = \json_decode($display, true);

        $this->assertNotSame($display1, $display2);
    }

    private function createCommandTester(array $paths = [], array $globals = [])
    {
        $filesystemLoader = new FilesystemLoader([], \dirname(__DIR__).'/Fixtures');
        foreach ($paths as $namespace => $relDirs) {
            foreach ($relDirs as $relDir) {
                $filesystemLoader->addPath($relDir, $namespace);
            }
        }

        $environment = new Environment($filesystemLoader);
        foreach ($globals as $name => $value) {
            $environment->addGlobal($name, $value);
        }

        $command = new DebugCommand($environment);

        $application = new Application();
        $application->add($command);
        $command = $application->find('debug:twig');

        return new CommandTester($command);
    }
}
