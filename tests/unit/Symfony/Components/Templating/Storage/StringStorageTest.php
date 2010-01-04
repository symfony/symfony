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
use Symfony\Components\Templating\Storage\StringStorage;

$t = new LimeTest(1);

$storage = new StringStorage('foo');
$t->ok($storage instanceof Storage, 'StringStorage is an instance of Storage');
