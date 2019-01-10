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

    private function createCommandTester(array $paths = [])
    {
        $filesystemLoader = new FilesystemLoader([], \dirname(__DIR__).'/Fixtures');
        foreach ($paths as $namespace => $relDirs) {
            foreach ($relDirs as $relDir) {
                $filesystemLoader->addPath($relDir, $namespace);
            }
        }
        $command = new DebugCommand(new Environment($filesystemLoader));

        $application = new Application();
        $application->add($command);
        $command = $application->find('debug:twig');

        return new CommandTester($command);
    }
}
