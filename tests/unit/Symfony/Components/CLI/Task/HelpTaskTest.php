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
use Symfony\Components\CLI\Task\HelpTask;
use Symfony\Components\CLI\Task\ListTask;
use Symfony\Components\CLI\Application;

$t = new LimeTest(4);

// ->execute()
$t->diag('->execute()');

$task = new HelpTask();
$task->setTask(new ListTask());

$taskTester = new TaskTester($task);
$taskTester->execute(array());
$t->like($taskTester->getDisplay(), '/list \[--xml\] \[namespace\]/', '->execute() returns a text help for the given task');

$taskTester->execute(array('--xml' => true));
$t->like($taskTester->getDisplay(), '/<task/', '->execute() returns an XML help text if --xml is passed');

$application = new Application();
$taskTester = new TaskTester($application->getTask('help'));
$taskTester->execute(array('task_name' => 'list'));
$t->like($taskTester->getDisplay(), '/list \[--xml\] \[namespace\]/', '->execute() returns a text help for the given task');

$taskTester->execute(array('task_name' => 'list', '--xml' => true));
$t->like($taskTester->getDisplay(), '/<task/', '->execute() returns an XML help text if --xml is passed');
