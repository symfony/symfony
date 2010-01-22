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
 * @version    SVN: $Id: LimeOutputConsoleSummary.php 25932 2009-12-27 19:55:32Z bschussek $
 */
class LimeOutputConsoleSummary implements LimeOutputInterface
{
  protected
    $printer        = null,
    $options        = array(),
    $startTime      = 0,
    $file           = null,
    $results        = array(),
    $actualFiles    = 0,
    $failedFiles    = 0,
    $actualTests    = 0,
    $expectedTests  = 0,
    $failedTests    = 0;

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
    if (!array_key_exists($file, $this->results))
    {
      $this->results[$file] = new LimeOutputResult();
    }

    $this->file = $file;
  }

  public function close()
  {
    if (!is_null($this->file))
    {
      $result = $this->results[$this->file];

      $this->actualFiles++;
      $this->actualTests += $result->getNbActual();
      $this->expectedTests += $result->getNbExpected();
      $this->failedTests += $result->getNbFailures();

      $path = $this->truncate($this->file);

      if (strlen($path) > 71)
      {
        $path = substr($path, -71);
      }

      $this->printer->printText(str_pad($path, 73, '.'));

      if ($result->hasErrors() || $result->hasFailures() || $result->isIncomplete())
      {
        $this->failedFiles++;
        $this->printer->printLine("not ok", LimePrinter::NOT_OK);
      }
      else if ($result->hasWarnings())
      {
        $this->printer->printLine("warning", LimePrinter::WARNING);
      }
      else
      {
        $this->printer->printLine("ok", LimePrinter::OK);
      }

      if ($result->isIncomplete())
      {
        $this->printer->printLine('    Plan Mismatch:', LimePrinter::COMMENT);
        if ($result->getNbActual() > $result->getNbExpected())
        {
          $this->printer->printLine(sprintf('    Looks like you only planned %s tests but ran %s.', $result->getNbExpected(), $result->getNbActual()));
        }
        else
        {
          $this->printer->printLine(sprintf('    Looks like you planned %s tests but only ran %s.', $result->getNbExpected(), $result->getNbActual()));
        }
      }

      if ($result->hasFailures())
      {
        $this->printer->printLine('    Failed Tests:', LimePrinter::COMMENT);

        $i = 0;
        foreach ($result->getFailures() as $number => $failed)
        {
          if (!$this->options['verbose'] && $i > 2)
          {
            $this->printer->printLine(sprintf('    ... and %s more', $result->getNbFailures()-$i));
            break;
          }

          ++$i;

          $this->printer->printLine('    not ok '.$number.' - '.$failed[0]);
        }
      }

      if ($result->hasWarnings())
      {
        $this->printer->printLine('    Warnings:', LimePrinter::COMMENT);

        foreach ($result->getWarnings() as $i => $warning)
        {
          if (!$this->options['verbose'] && $i > 2)
          {
            $this->printer->printLine(sprintf('    ... and %s more', $result->getNbWarnings()-$i));
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

      if ($result->hasErrors())
      {
        $this->printer->printLine('    Errors:', LimePrinter::COMMENT);

        foreach ($result->getErrors() as $i => $error)
        {
          if (!$this->options['verbose'] && $i > 2)
          {
            $this->printer->printLine(sprintf('    ... and %s more', $result->getNbErrors()-$i));
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

      if ($result->hasTodos())
      {
        $this->printer->printLine('    TODOs:', LimePrinter::COMMENT);

        foreach ($result->getTodos() as $i => $todo)
        {
          if (!$this->options['verbose'] && $i > 2)
          {
            $this->printer->printLine(sprintf('    ... and %s more', $result->getNbTodos()-$i));
            break;
          }

          $this->printer->printLine('    '.$todo);
        }
      }
    }
  }

  public function plan($amount)
  {
    $this->results[$this->file]->addPlan($amount);
  }

  public function pass($message, $file, $line)
  {
    $this->results[$this->file]->addPassed();
  }

  public function fail($message, $file, $line, $error = null)
  {
    $this->results[$this->file]->addFailure(array($message, $file, $line, $error));
  }

  public function skip($message, $file, $line)
  {
    $this->results[$this->file]->addSkipped();
  }

  public function todo($message, $file, $line)
  {
    $this->results[$this->file]->addTodo($message);
  }

  public function warning($message, $file, $line)
  {
    $this->results[$this->file]->addWarning(array($message, $file, $line));
  }

  public function error(LimeError $error)
  {
    $this->results[$this->file]->addError($error);
  }

  public function comment($message) {}

  public function flush()
  {
    if ($this->failedFiles > 0)
    {
      $stats = sprintf(' Failed %d/%d test scripts, %.2f%% okay. %d/%d subtests failed, %.2f%% okay.',
          $this->failedFiles, $this->actualFiles, 100 - 100*$this->failedFiles/max(1,$this->actualFiles),
          $this->failedTests, $this->expectedTests, 100 - 100*$this->failedTests/max(1,$this->expectedTests));

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