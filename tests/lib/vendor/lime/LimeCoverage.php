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

class LimeCoverage extends LimeRegistration
{
  const
    COVERED       = 1,
    UNCOVERED     = -1;

  protected
    $options        = array(),
    $files          = array(),
    $suite          = null,
    $coverage       = array(),
    $coveredLines   = 0,
    $uncoveredLines = 0,
    $coveredCode    = array(),
    $uncoveredCode  = array(),
    $actualCode     = array();

  public function __construct(LimeTestSuite $suite, array $options = array())
  {
    $this->suite = $suite;
    $this->options = array_merge(array(
      'base_dir'  => null,
      'extension' => '.php',
      'verbose'   => false,
    ), $options);

    // temporary solution, LimeRegistration needs to be modified
    $this->setBaseDir($this->options['base_dir']);
    $this->setExtension($this->options['extension']);

    if (!function_exists('xdebug_start_code_coverage'))
    {
      throw new Exception('You must install and enable xdebug before using lime coverage.');
    }

    if (!ini_get('xdebug.extended_info'))
    {
      throw new Exception('You must set xdebug.extended_info to 1 in your php.ini to use lime coverage.');
    }
  }

  public function setFiles($files)
  {
    if (!is_array($files))
    {
      $files = array($files);
    }

  	$this->files = $files;
  }

  public function run()
  {
    if (!count($this->suite->files))
    {
      throw new Exception('You must register some test files before running coverage!');
    }

    if (!count($this->files))
    {
      throw new Exception('You must register some files to cover!');
    }

    $this->coverage = array();

    $this->process($this->suite->files);
    $this->parseCoverage($this->coverage);

    $this->render();
  }

  protected function process(array $files)
  {
    $this->output = new LimeOutput();

    foreach ($files as $file)
    {
      $command = new LimeShellCommand($file, array('coverage' => true));
      $command->execute();

      // script failed
      if ($command->getStatus() != LimeShell::SUCCESS)
      {
        $this->output->echoln(sprintf('Warning: %s returned status %d, results may be inaccurate', $file, $command->getStatus()), LimeOutput::ERROR);
      }

      // script succeeded, coverage not readable
      if (false === $coverage = @unserialize($command->getOutput()))
      {
        if ($command->getStatus() == LimeShell::SUCCESS)
        {
          throw new Exception(sprintf('Unable to unserialize coverage for file "%s"', $file));
        }
      }
      else
      {
        foreach ($coverage as $file => $lines)
        {
          if (!isset($this->coverage[$file]))
          {
            $this->coverage[$file] = $lines;
          }
          else
          {
            foreach ($lines as $line => $flag)
            {
              if ($flag == self::COVERED)
              {
                $this->coverage[$file][$line] = 1;
              }
            }
          }
        }
      }
    }
  }

  protected function parseCoverage(array $coverage)
  {
    $this->coveredLines = 0;
    $this->uncoveredLines = 0;

    ksort($coverage);

    foreach ($coverage as $file => $lines)
    {
      $this->coveredCode[$file] = array();
      $this->uncoveredCode[$file] = array();

      foreach ($lines as $line => $flag)
      {
        if ($flag == self::COVERED)
        {
          $this->coveredCode[$file][] = $line;
          $this->coveredLines++;
        }
        else
        {
          $this->uncoveredCode[$file][] = $line;
          $this->uncoveredLines++;
        }
      }
    }

    $lexer = new LimeLexerCodeLines();

    foreach ($this->files as $file)
    {
      if (!array_key_exists($file, $this->coveredCode))
      {
        $this->coveredCode[$file] = array();
        $this->uncoveredCode[$file] = $lexer->parse($file);
        $this->uncoveredLines += count($this->uncoveredCode[$file]);
      }
    }
  }

  protected function render()
  {
    $printer = new LimePrinter(new LimeColorizer());

    foreach ($this->files as $file)
    {
      $totalLines = count($this->coveredCode[$file]) + count($this->uncoveredCode[$file]);
      $percent = count($this->coveredCode[$file]) * 100 / max($totalLines, 1);

      $relativeFile = $this->getRelativeFile($file);

      if ($percent == 100)
      {
        $style = LimePrinter::OK;
      }
      else if ($percent >= 90)
      {
        $style = LimePrinter::INFO;
      }
      else if ($percent <= 20)
      {
        $style = LimePrinter::NOT_OK;
      }
      else
      {
        $style = null;
      }

      $printer->printLine(sprintf("%-76s%3.0f%%", $relativeFile, $percent), $style);

      if ($this->options['verbose'] && $percent > 0 && $percent < 100)
      {
        $printer->printLine(sprintf("missing: %s", $this->formatRange($this->uncoveredCode[$file])), LimePrinter::COMMENT);
      }
    }

    $totalLines = $this->coveredLines + $this->uncoveredLines;
    $percent = $this->coveredLines * 100 / max($totalLines, 1);

    if ($percent <= 20)
    {
      $style = LimePrinter::NOT_OK;
    }
    else
    {
      $style = LimePrinter::HAPPY;
    }

    $printer->printLine(str_pad(sprintf(" Total Coverage: %3.0f%%", $percent), 80), $style);
  }

  protected function formatRange(array $lines)
  {
    sort($lines);
    $formatted = '';
    $first = -1;
    $last = -1;
    foreach ($lines as $line)
    {
      if ($last + 1 != $line)
      {
        if ($first != -1)
        {
          $formatted .= $first == $last ? "$first " : "[$first - $last] ";
        }
        $first = $line;
        $last = $line;
      }
      else
      {
        $last = $line;
      }
    }
    if ($first != -1)
    {
      $formatted .= $first == $last ? "$first " : "[$first - $last] ";
    }

    return $formatted;
  }
}