<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Tester;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Console\Application;
use Symfony\Components\Console\Output\Output;
use Symfony\Components\Console\Tester\ApplicationTester;

class ApplicationTesterTest extends \PHPUnit_Framework_TestCase
{
  protected $application;
  protected $tester;

  public function setUp()
  {
    $this->application = new Application();
    $this->application->setAutoExit(false);
    $this->application->register('foo')
      ->addArgument('command')
      ->addArgument('foo')
      ->setCode(function ($input, $output) { $output->writeln('foo'); })
    ;

    $this->tester = new ApplicationTester($this->application);
    $this->tester->run(array('command' => 'foo', 'foo' => 'bar'), array('interactive' => false, 'decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE));
  }

  public function testRun()
  {
    $this->assertEquals($this->tester->getInput()->isInteractive(), false, '->execute() takes an interactive option');
    $this->assertEquals($this->tester->getOutput()->isDecorated(), false, '->execute() takes a decorated option');
    $this->assertEquals($this->tester->getOutput()->getVerbosity(), Output::VERBOSITY_VERBOSE, '->execute() takes a verbosity option');
  }

  public function testGetInput()
  {
    $this->assertEquals($this->tester->getInput()->getArgument('foo'), 'bar', '->getInput() returns the current input instance');
  }

  public function testGetOutput()
  {
    rewind($this->tester->getOutput()->getStream());
    $this->assertEquals(stream_get_contents($this->tester->getOutput()->getStream()), "foo\n", '->getOutput() returns the current output instance');
  }

  public function testGetDisplay()
  {
    $this->assertEquals($this->tester->getDisplay(), "foo\n", '->getDisplay() returns the display of the last execution');
  }
}
