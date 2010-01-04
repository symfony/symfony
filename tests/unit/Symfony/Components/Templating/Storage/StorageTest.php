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

use Symfony\Components\Templating\Storage\Storage;
use Symfony\Components\Templating\Renderer\PhpRenderer;

$t = new LimeTest(2);

// __construct() __toString()
$t->diag('__construct() __toString()');

$storage = new Storage('foo');
$t->is((string) $storage, 'foo', '__toString() returns the template name');

// ->getRenderer()
$t->diag('->getRenderer()');
$storage = new Storage('foo', $renderer = new PhpRenderer());
$t->ok($storage->getRenderer() === $renderer, '->getRenderer() returns the renderer');
