<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

require_once __DIR__.'/../../../../../lib/SymfonyTests/Components/Templating/ProjectTemplateDebugger.php';

use Symfony\Components\Templating\Loader\Loader;

$t = new LimeTest(1);

class ProjectTemplateLoader extends Loader
{
  public function load($template, $renderer = 'php')
  {
  }

  public function getDebugger()
  {
    return $this->debugger;
  }
}

// ->setDebugger()
$t->diag('->setDebugger()');
$loader = new ProjectTemplateLoader();
$loader->setDebugger($debugger = new ProjectTemplateDebugger());
$t->ok($loader->getDebugger() === $debugger, '->setDebugger() sets the debugger instance');
