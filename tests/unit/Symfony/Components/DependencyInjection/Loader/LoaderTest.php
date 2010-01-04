<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Loader\Loader;

require_once __DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/includes/ProjectExtension.php';

class ProjectLoader extends Loader
{
  public function load($resource)
  {
  }
}

$t = new LimeTest(1);

// ::registerExtension() ::getExtension()
$t->diag('::registerExtension() ::getExtension()');
ProjectLoader::registerExtension($extension = new ProjectExtension());
$t->ok(ProjectLoader::getExtension('project') === $extension, '::registerExtension() registers an extension');
