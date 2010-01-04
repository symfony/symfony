<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Dumper\Dumper;

$t = new LimeTest(1);

class ProjectDumper extends Dumper
{
}

$builder = new Builder();
$dumper = new ProjectDumper($builder);
try
{
  $dumper->dump();
  $t->fail('->dump() returns a LogicException if the dump() method has not been overriden by a children class');
}
catch (LogicException $e)
{
  $t->pass('->dump() returns a LogicException if the dump() method has not been overriden by a children class');
}
