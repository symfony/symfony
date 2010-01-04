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

class LimeOutputTap implements LimeOutputInterface
{
  protected
    $options    = array(),
    $expected   = null,
    $passed     = 0,
    $actual     = 0,
    $warnings   = 0,
    $errors     = 0,
    $file       = null,
    $printer    = null;

  public function __construct(LimePrinter $printer, array $options = array())
  {
    $this->printer = $printer;
    $this->options = array_merge(array(
      'verbose'   => false,
      'base_dir'  => null,
    ), $options);
  }

  public function supportsThreading()
  {
    return false;
  }

  private function stripBaseDir($path)
  {
    return is_null($this->options['base_dir']) ? $path : str_replace($this->options['base_dir'], '', $path);
  }

  public function focus($file)
  {
    if ($this->file !== $file)
    {
      $this->printer->printLine('# '.$this->stripBaseDir($file), LimePrinter::INFO);

      $this->file = $file;
    }
  }

  public function close()
  {
  }

  public function plan($amount)
  {
    $this->expected += $amount;
  }

  public function pass($message, $file, $line)
  {
    $this->actual++;
    $this->passed++;

    if (empty($message))
    {
      $this->printer->printLine('ok '.$this->actual, LimePrinter::OK);
    }
    else
    {
      $this->printer->printText('ok '.$this->actual, LimePrinter::OK);
      $this->printer->printLine(' - '.$message);
    }
  }

  public function fail($message, $file, $line, $error = null)
  {
    $this->actual++;

    if (empty($message))
    {
      $this->printer->printLine('not ok '.$this->actual, LimePrinter::NOT_OK);
    }
    else
    {
      $this->printer->printText('not ok '.$this->actual, LimePrinter::NOT_OK);
      $this->printer->printLine(' - '.$message);
    }

    $this->printer->printLine(sprintf('#     Failed test (%s at line %s)', $this->stripBaseDir($file), $line), LimePrinter::COMMENT);

    if (!is_null($error))
    {
      foreach (explode("\n", $error) as $line)
      {
        $this->printer->printLine('#       '.$line, LimePrinter::COMMENT);
      }
    }
  }

  public function skip($message, $file, $line)
  {
    $this->actual++;
    $this->passed++;

    if (empty($message))
    {
      $this->printer->printText('ok '.$this->actual, LimePrinter::SKIP);
      $this->printer->printText(' ');
    }
    else
    {
      $this->printer->printText('ok '.$this->actual, LimePrinter::SKIP);
      $this->printer->printText(' - '.$message.' ');
    }

    $this->printer->printLine('# SKIP', LimePrinter::SKIP);
  }

  public function todo($message, $file, $line)
  {
    $this->actual++;
    $this->passed++;

    if (empty($message))
    {
      $this->printer->printText('not ok '.$this->actual, LimePrinter::TODO);
      $this->printer->printText(' ');
    }
    else
    {
      $this->printer->printText('not ok '.$this->actual, LimePrinter::TODO);
      $this->printer->printText(' - '.$message.' ');
    }

    $this->printer->printLine('# TODO', LimePrinter::TODO);
  }

  public function warning($message, $file, $line)
  {
    $this->warnings++;

    $message .= sprintf("\n(in %s on line %s)", $this->stripBaseDir($file), $line);

    $this->printer->printLargeBox($message, LimePrinter::WARNING);
  }

  public function error(LimeError $error)
  {
    $this->errors++;

    $message = sprintf("%s: %s\n(in %s on line %s)", $error->getType(),
        $error->getMessage(), $this->stripBaseDir($error->getFile()), $error->getLine());

    $this->printer->printLargeBox($message, LimePrinter::ERROR);

    $this->printer->printLine('Exception trace:', LimePrinter::COMMENT);

    $this->printTrace(null, $error->getFile(), $error->getLine());

    foreach ($error->getTrace() as $trace)
    {
      // hide the part of the trace that is responsible for getting the
      // annotations to work
      if (strpos($trace['function'], '__lime_annotation_') === 0 && !$this->options['verbose'])
      {
        break;
      }

      if (array_key_exists('class', $trace))
      {
        $method = sprintf('%s%s%s()', $trace['class'], $trace['type'], $trace['function']);
      }
      else
      {
        $method = sprintf('%s()', $trace['function']);
      }

      if (array_key_exists('file', $trace))
      {
        $this->printTrace($method, $trace['file'], $trace['line']);
      }
      else
      {
        $this->printTrace($method);
      }
    }

    $this->printer->printLine('');
  }

  private function printTrace($method = null, $file = null, $line = null)
  {
    if (!is_null($method))
    {
      $method .= ' ';
    }

    $this->printer->printText('  '.$method.'at ');

    if (!is_null($file) && !is_null($line))
    {
      $this->printer->printText($this->stripBaseDir($file), LimePrinter::TRACE);
      $this->printer->printText(':');
      $this->printer->printLine($line, LimePrinter::TRACE);
    }
    else
    {
      $this->printer->printLine('[internal function]');
    }
  }

  public function info($message)
  {
    $this->printer->printLine('# '.$message, LimePrinter::INFO);
  }

  public function comment($message)
  {
    $this->printer->printLine('# '.$message, LimePrinter::COMMENT);
  }

  public static function getMessages($actual, $expected, $passed, $errors, $warnings)
  {
    $messages = array();

    if ($passed == $expected && $passed === $actual && $errors == 0)
    {
      if ($warnings > 0)
      {
        $messages[] = array('Looks like you\'re nearly there.', LimePrinter::WARNING);
      }
      else
      {
        $messages[] = array('Looks like everything went fine.', LimePrinter::HAPPY);
      }
    }
    else if ($passed != $actual)
    {
      $messages[] = array(sprintf('Looks like you failed %s tests of %s.', $actual - $passed, $actual), LimePrinter::ERROR);
    }
    else if ($errors > 0)
    {
      $messages[] = array('Looks like some errors occurred.', LimePrinter::ERROR);
    }

    if ($actual > $expected && $expected > 0)
    {
      $messages[] = array(sprintf('Looks like you only planned %s tests but ran %s.', $expected, $actual), LimePrinter::ERROR);
    }
    else if ($actual < $expected)
    {
      $messages[] = array(sprintf('Looks like you planned %s tests but only ran %s.', $expected, $actual), LimePrinter::ERROR);
    }

    return $messages;
  }

  public function flush()
  {
    if (is_null($this->expected))
    {
      $this->plan($this->actual);
    }

    $this->printer->printLine('1..'.$this->expected);

    $messages = self::getMessages($this->actual, $this->expected, $this->passed, $this->errors, $this->warnings);

    foreach ($messages as $message)
    {
      list ($message, $style) = $message;

      $this->printer->printBox(' '.$message, $style);
    }
  }
}