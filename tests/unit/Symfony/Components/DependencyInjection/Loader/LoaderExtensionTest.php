<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

require_once __DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/includes/ProjectExtension.php';

$t = new LimeTest(2);

// ->load()
$t->diag('->load()');
$extension = new ProjectExtension();

try
{
  $extension->load('foo', array());
  $t->fail('->load() throws an InvalidArgumentException if the tag does not exist');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if the tag does not exist');
}

$config = $extension->load('bar', array('foo' => 'bar'));
$t->is($config->getParameters(), array('project.parameter.bar' => 'bar'), '->load() calls the method tied to the given tag');
