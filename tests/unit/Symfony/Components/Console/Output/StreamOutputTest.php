<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Output\Output;
use Symfony\Components\Console\Output\StreamOutput;

$t = new LimeTest(5);

$stream = fopen('php://memory', 'a', false);

// __construct()
$t->diag('__construct()');

try
{
  $output = new StreamOutput('foo');
  $t->fail('__construct() throws an \InvalidArgumentException if the first argument is not a stream');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('__construct() throws an \InvalidArgumentException if the first argument is not a stream');
}

$output = new StreamOutput($stream, Output::VERBOSITY_QUIET, true);
$t->is($output->getVerbosity(), Output::VERBOSITY_QUIET, '__construct() takes the verbosity as its first argument');
$t->is($output->isDecorated(), true, '__construct() takes the decorated flag as its second argument');

// ->getStream()
$t->diag('->getStream()');
$output = new StreamOutput($stream);
$t->is($output->getStream(), $stream, '->getStream() returns the current stream');

// ->doWrite()
$t->diag('->doWrite()');
$output = new StreamOutput($stream);
$output->write('foo');
rewind($output->getStream());
$t->is(stream_get_contents($output->getStream()), "foo\n", '->doWrite() writes to the stream');
