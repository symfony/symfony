<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Output\NullOutput;

$t = new LimeTest(1);

$output = new NullOutput();
$output->write('foo');
$t->pass('->write() does nothing');
