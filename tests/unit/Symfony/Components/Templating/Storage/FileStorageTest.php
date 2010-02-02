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
use Symfony\Components\Templating\Storage\FileStorage;

$t = new LimeTest(2);

$storage = new FileStorage('foo');
$t->ok($storage instanceof Storage, 'FileStorage is an instance of Storage');

// ->getContent()
$t->diag('->getContent()');
$storage = new FileStorage(__DIR__.'/../../../../../fixtures/Symfony/Components/Templating/templates/foo.php');
$t->is($storage->getContent(), '<?php echo $foo ?>', '->getContent() returns the content of the template');
