<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\DependencyInjection\FileResource;

$t = new LimeTest(4);

// ->getResource()
$t->diag('->getResource()');
$file = sys_get_temp_dir().'/tmp.xml';
touch($file);
$resource = new FileResource($file);
$t->is($resource->getResource(), $file, '->getResource() returns the path to the resource');

// ->isUptodate()
$t->diag('->isUptodate()');
$t->ok($resource->isUptodate(time() + 10), '->isUptodate() returns true if the resource has not changed');
$t->ok(!$resource->isUptodate(time() - 86400), '->isUptodate() returns false if the resource has been updated');
unlink($file);

$resource = new FileResource('/____foo/foobar'.rand(1, 999999));
$t->ok(!$resource->isUptodate(time()), '->isUptodate() returns false if the resource does not exist');
