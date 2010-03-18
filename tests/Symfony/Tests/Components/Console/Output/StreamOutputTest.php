<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Console\Output;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\Console\Output\Output;
use Symfony\Components\Console\Output\StreamOutput;

class StreamOutputTest extends \PHPUnit_Framework_TestCase
{
  protected $stream;

  public function setUp()
  {
    $this->stream = fopen('php://memory', 'a', false);
  }

  public function testConstructor()
  {
    try
    {
      $output = new StreamOutput('foo');
      $this->fail('__construct() throws an \InvalidArgumentException if the first argument is not a stream');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    $output = new StreamOutput($this->stream, Output::VERBOSITY_QUIET, true);
    $this->assertEquals($output->getVerbosity(), Output::VERBOSITY_QUIET, '__construct() takes the verbosity as its first argument');
    $this->assertEquals($output->isDecorated(), true, '__construct() takes the decorated flag as its second argument');
  }

  public function testGetStream()
  {
    $output = new StreamOutput($this->stream);
    $this->assertEquals($output->getStream(), $this->stream, '->getStream() returns the current stream');
  }

  public function testDoWrite()
  {
    $output = new StreamOutput($this->stream);
    $output->writeln('foo');
    rewind($output->getStream());
    $this->assertEquals(stream_get_contents($output->getStream()), "foo\n", '->doWrite() writes to the stream');
  }
}
