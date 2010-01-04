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

use Symfony\Components\Templating\Renderer\PhpRenderer;
use Symfony\Components\Templating\Storage\Storage;
use Symfony\Components\Templating\Storage\StringStorage;
use Symfony\Components\Templating\Storage\FileStorage;

$t = new LimeTest(2);

$renderer = new PhpRenderer();

// ->evaluate()
$t->diag('->evaluate()');

$template = new StringStorage('<?php echo $foo ?>');
$t->is($renderer->evaluate($template, array('foo' => 'bar')), 'bar', '->evaluate() renders templates that are instances of StringStorage');

$template = new FileStorage(__DIR__.'/../../../../../fixtures/Symfony/Components/Templating/templates/foo.php');
$t->is($renderer->evaluate($template, array('foo' => 'bar')), 'bar', '->evaluate() renders templates that are instances of FileStorage');
