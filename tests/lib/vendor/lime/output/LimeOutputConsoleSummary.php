<?php

/*
 * This file is part of the Lime test framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * (c) Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Colorizes test results and summarizes them in the console.
 *
 * For each test file, one line is printed in the console with a few optional
 * lines in case the file contains errors or failed tests.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeOutputConsoleSummary.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeOutputConsoleSummary implements LimeOutputInterface
{
  protected
    $printer        = null,
    $options        = array(),
    $startTime      = 0,
    $file           = null,
    $actualFiles    = 0,
    $failedFiles    = 0,
    $actualTests    = 0,
    $failedTests    = 0,
    $expected       = array(),
    $actual         = array(),
    $passed         = array(),
    $failed         = array(),
    $errors         = array(),
    $warnings       = array(),
    $todos          = array(),
    $line           = array();

  /**
   * Constructor.
   *
   * @param LimePrinter $printer  The printer for printing text to the console
   * @param array       $options  The options of this output
   */
  public function __construct(LimePrinter $printer, array $options = array())
  {
    $this->printer = $printer;
    $this->startTime = time();
    $this->options = array_merge(array(
      'base_dir'  => null,
      'processes' => 1,
      'verbose'   => false,
    ), $options);
  }

  public function supportsThreading()
  {
    return true;
  }

  public function focus($file)
  {
    $this->file = $file;

    if (!array_key_exists($file, $this->line))
    {
      $this->line[$file] = count($this->line);
      $this->expected[$file] = 0;
      $this->actual[$file] = 0;
      $this->passed[$file] = 0;
      $this->failed[$file] = array();
      $this->errors[$file] = array();
      $this->warnings[$file] = array();
      $this->todos[$file] = array();
    }
  }

  public function close()
  {
    if (!is_null($this->file))
    {
      $this->actualFiles++;
      $this->actualTests += $this->getActual();
      $this->failedTests += $this->getFailed();

      $path = $this->truncate($this->file);

      if (strlen($path) > 71)
      {
        $path = substr($path, -71);
      }

      $this->printer->printText(str_pad($path, 73, '.'));

      $incomplete = ($this->getExpected() > 0 && $this->getActual() != $this->getExpected());

      if ($this->getErrors() || $this->getFailed() || $incomplete)
      {
        $this->failedFiles++;
        $this->printer->printLine("not ok", LimePrinter::NOT_OK);
      }
      else if ($this->getWarnings())
      {
        $this->printer->printLine("warning", LimePrinter::WARNING);
      }
      else
      {
        $this->printer->printLine("ok", LimePrinter::OK);
      }

      if ($this->getExpected() > 0 && $this->getActual() != $this->getExpected())
      {
        $this->printer->printLine('    Plan Mismatch:', LimePrinter::COMMENT);
        if ($this->getActual() > $this->getExpected())
        {
          $this->printer->printLine(sprintf('    Looks like you only planned %s tests but ran %s.', $this->getExpected(), $this->getActual()));
        }
        else
        {
          $this->printer->printLine(sprintf('    Looks like you planned %s tests but only ran %s.', $this->getExpected(), $this->getActual()));
        }
      }

      if ($this->getFailed())
      {
        $this->printer->printLine('    Failed Tests:', LimePrinter::COMMENT);

        $i = 0;
        foreach ($this->failed[$this->file] as $number => $failed)
        {
          if (!$this->options['verbose'] && $i > 2)
          {
            $this->printer->printLine(sprintf('    ... and %s more', $this->getFailed()-$i));
            break;
          }

          ++$i;

          $this->printer->printLine('    not ok '.$number.' - '.$failed[0]);
        }
      }

      if ($this->getWarnings())
      {
        $this->printer->printLine('    Warnings:', LimePrinter::COMMENT);

        foreach ($this->warnings[$this->file] as $i => $warning)
        {
          if (!$this->options['verbose'] && $i > 2)
          {
            $this->printer->printLine(sprintf('    ... and %s more', $this->getWarnings()-$i));
            break;
          }

          $this->printer->printLine('    '.$warning[0]);

          if ($this->options['verbose'])
          {
            $this->printer->printText('      (in ');
            $this->printer->printText($this->truncate($warning[1]), LimePrinter::TRACE);
            $this->printer->printText(' on line ');
            $this->printer->printText($warning[2], LimePrinter::TRACE);
            $this->printer->printLine(')');
          }
        }
      }

      if ($this->getErrors())
      {
        $this->printer->printLine('    Errors:', LimePrinter::COMMENT);

        foreach ($this->errors[$this->file] as $i => $error)
        {
          if (!$this->options['verbose'] && $i > 2)
          {
            $this->printer->printLine(sprintf('    ... and %s more', $this->getErrors()-$i));
            break;
          }

          $this->printer->printLine('    '.$error->getMessage());

          if ($this->options['verbose'])
          {
            $this->printer->printText('      (in ');
            $this->printer->printText($this->truncate($error->getFile()), LimePrinter::TRACE);
            $this->printer->printText(' on line ');
            $this->printer->printText($error->getLine(), LimePrinter::TRACE);
            $this->printer->printLine(')');
          }
        }
      }

      if ($this->getTodos())
      {
        $this->printer->printLine('    TODOs:', LimePrinter::COMMENT);

        foreach ($this->todos[$this->file] as $i => $todo)
        {
          if (!$this->options['verbose'] && $i > 2)
          {
            $this->printer->printLine(sprintf('    ... and %s more', $this->getTodos()-$i));
            break;
          }

          $this->printer->printLine('    '.$todo);
        }
      }
    }
  }

  protected function getExpected()
  {
    return $this->expected[$this->file];
  }

  protected function getActual()
  {
    return $this->actual[$this->file];
  }

  protected function getPassed()
  {
    return $this->passed[$this->file];
  }

  protected function getFailed()
  {
    return count($this->failed[$this->file]);
  }

  protected function getErrors()
  {
    return count($this->errors[$this->file]);
  }

  protected function getWarnings()
  {
    return count($this->warnings[$this->file]);
  }

  protected function getTodos()
  {
    return count($this->todos[$this->file]);
  }

  public function plan($amount)
  {
    $this->expected[$this->file] = $amount;
  }

  public function pass($message, $file, $line)
  {
    $this->passed[$this->file]++;
    $this->actual[$this->file]++;
  }

  public function fail($message, $file, $line, $error = null)
  {
    $this->actual[$this->file]++;
    $this->failed[$this->file][$this->actual[$this->file]] = array($message, $file, $line, $error);
  }

  public function skip($message, $file, $line)
  {
    $this->actual[$this->file]++;
  }

  public function todo($message, $file, $line)
  {
    $this->actual[$this->file]++;
    $this->todos[$this->file][] = $message;
  }

  public function warning($message, $file, $line)
  {
    $this->warnings[$this->file][] = array($message, $file, $line);
  }

  public function error(LimeError $error)
  {
    $this->errors[$this->file][] = $error;
  }

  public function comment($message) {}

  public function flush()
  {
    if ($this->failedFiles > 0)
    {
      $stats = sprintf(' Failed %d/%d test scripts, %.2f%% okay. %d/%d subtests failed, %.2f%% okay.',
          $this->failedFiles, $this->actualFiles, 100 - 100*$this->failedFiles/max(1,$this->actualFiles),
          $this->failedTests, $this->actualTests, 100 - 100*$this->failedTests/max(1,$this->actualTests));

      $this->printer->printBox($stats, LimePrinter::NOT_OK);
    }
    else
    {
      $time = max(1, time() - $this->startTime);
      $stats = sprintf(' Files=%d, Tests=%d, Time=%02d:%02d, Processes=%d',
          $this->actualFiles, $this->actualTests, floor($time/60), $time%60, $this->options['processes']);

      $this->printer->printBox(' All tests successful.', LimePrinter::HAPPY);
      $this->printer->printBox($stats, LimePrinter::HAPPY);
    }
  }

  protected function truncate($file)
  {
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    $file = substr($file, 0, strlen($file)-strlen($extension));

    if (!is_null($this->options['base_dir']))
    {
      return str_replace($this->options['base_dir'], '', $file);
    }
    else
    {
      return $file;
    }
  }
}