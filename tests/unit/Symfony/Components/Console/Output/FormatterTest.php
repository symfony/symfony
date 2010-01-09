<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Output\Formatter;

$t = new LimeTest(4);

// ::formatSection()
$t->diag('::formatSection()');
$t->is(Formatter::formatSection('cli', 'Some text to display'), '<info>[cli]</info> Some text to display', '::formatSection() formats a message in a section');

// ::formatBlock()
$t->diag('::formatBlock()');
$t->is(Formatter::formatBlock('Some text to display', 'error'), '<error> Some text to display </error>', '::formatBlock() formats a message in a block');
$t->is(Formatter::formatBlock(array('Some text to display', 'foo bar'), 'error'), "<error> Some text to display </error>\n<error> foo bar              </error>", '::formatBlock() formats a message in a block');

$t->is(Formatter::formatBlock('Some text to display', 'error', true), "<error>                        </error>\n<error>  Some text to display  </error>\n<error>                        </error>", '::formatBlock() formats a message in a block');
