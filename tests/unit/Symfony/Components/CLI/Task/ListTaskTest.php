<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\CLI\Tester\TaskTester;
use Symfony\Components\CLI\Application;

$t = new LimeTest(2);

$application = new Application();

// ->execute()
$t->diag('->execute()');

$taskTester = new TaskTester($application->getTask('list'));
$taskTester->execute(array());
$t->like($taskTester->getDisplay(), '/help   Displays help for a task/', '->execute() returns a list of available tasks');

$taskTester->execute(array('--xml' => true));
$t->like($taskTester->getDisplay(), '/<task id="list" namespace="_global" name="list">/', '->execute() returns a list of available tasks in XML if --xml is passed');
