<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Application;
use Symfony\Components\Console\Output\Output;
use Symfony\Components\Console\Tester\ApplicationTester;

$t = new LimeTest(6);

$application = new Application();
$application->setAutoExit(false);
$application->register('foo')
  ->addArgument('command')
  ->addArgument('foo')
  ->setCode(function ($input, $output) { $output->write('foo'); })
;

$tester = new ApplicationTester($application);
$tester->run(array('command' => 'foo', 'foo' => 'bar'), array('interactive' => false, 'decorated' => false, 'verbosity' => Output::VERBOSITY_VERBOSE));

// ->run()
$t->diag('->run()');
$t->is($tester->getInput()->isInteractive(), false, '->execute() takes an interactive option');
$t->is($tester->getOutput()->isDecorated(), false, '->execute() takes a decorated option');
$t->is($tester->getOutput()->getVerbosity(), Output::VERBOSITY_VERBOSE, '->execute() takes a verbosity option');

// ->getInput()
$t->diag('->getInput()');
$t->is($tester->getInput()->getArgument('foo'), 'bar', '->getInput() returns the current input instance');

// ->getOutput()
$t->diag('->getOutput()');
rewind($tester->getOutput()->getStream());
$t->is(stream_get_contents($tester->getOutput()->getStream()), "foo\n", '->getOutput() returns the current output instance');

// ->getDisplay()
$t->diag('->getDisplay()');
$t->is($tester->getDisplay(), "foo\n", '->getDisplay() returns the display of the last execution');
