<?php

/*
 * This file is part of the Lime framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class LimeTestAnalyzer
{
  protected
    $suppressedMethods  = array(),
    $output             = null,
    $errors             = '',
    $file               = null,
    $process            = null,
    $done               = true,
    $parser             = null;

  public function __construct(LimeOutputInterface $output, array $suppressedMethods = array())
  {
    $this->suppressedMethods = $suppressedMethods;
    $this->output = $output;
  }

  public function getConnectedFile()
  {
    return $this->file;
  }

  public function connect($file, array $arguments = array())
  {
    $arguments['output'] = 'raw';

    $this->file = $file;
    $this->done = false;
    $this->parser = null;
    $this->process = new LimeShellProcess($file, $arguments);
    $this->process->execute();
  }

  public function proceed()
  {
    $data = $this->process->getOutput();

    if (is_null($this->parser))
    {
      if (substr($data, 0, 5) == "\0raw\0")
      {
        $this->parser = new LimeParserRaw($this->output, $this->suppressedMethods);
        $data = substr($data, 5);
      }
      else
      {
        $this->parser = new LimeParserTap($this->output);
      }
    }

    $this->parser->parse($data);

    $this->errors .= $this->process->getErrors();

    while (preg_match('/^(.+)\n/', $this->errors, $matches))
    {
      $this->output->warning($matches[1], $this->file, 0);
      $this->errors = substr($this->errors, strlen($matches[0]));
    }

    if ($this->process->isClosed())
    {
      if (!$this->parser->done())
      {
        // FIXME: Should be handled in a better way
        $buffer = substr($this->parser->buffer, 0, strpos($this->parser->buffer, "\n"));
        $this->output->warning(sprintf('Could not parse test output: "%s"', $buffer), $this->file, 1);
      }

      // if the last error was not followed by \n, it is still in the buffer
      if (!empty($this->errors))
      {
        $this->output->warning($this->errors, $this->file, 0);
        $this->errors = '';
      }

      $this->done = true;
    }
  }

  public function done()
  {
    return $this->done;
  }
}