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

class LimeTestSuite extends LimeRegistration
{
  protected
    $options    = array(),
    $executable = null,
    $output     = null;

  public function __construct(array $options = array())
  {
    $this->options = array_merge(array(
      'base_dir'     => null,
      'executable'   => null,
      'output'       => 'summary',
      'force_colors' => false,
      'verbose'      => false,
      'serialize'    => false,
      'processes'    => 1,
    ), $options);

    foreach (LimeShell::parseArguments($GLOBALS['argv']) as $argument => $value)
    {
      $this->options[str_replace('-', '_', $argument)] = $value;
    }

    $this->options['base_dir'] = realpath($this->options['base_dir']);

    if (is_string($this->options['output']))
    {
      $factory = new LimeOutputFactory($this->options);

      $type = $this->options['output'];
      $output = $factory->create($type);
    }
    else
    {
      $output = $this->options['output'];
      $type = get_class($output);
    }

    if ($this->options['processes'] > 1 && !$output->supportsThreading())
    {
      throw new LogicException(sprintf('The output "%s" does not support multi-processing', $type));
    }

    $this->output = new LimeOutputInspectable($output);
  }

  public function run()
  {
    if (!count($this->files))
    {
      throw new Exception('You must register some test files before running them!');
    }

    // sort the files to be able to predict the order
    sort($this->files);
    reset($this->files);

    $connectors = array();

    for ($i = 0; $i < $this->options['processes']; ++$i)
    {
      $connectors[] = new LimeTestAnalyzer($this->output, array('focus', 'close', 'flush'));
    }

    do
    {
      $done = true;

      foreach ($connectors as $connector)
      {
        if ($connector->done() && !is_null(key($this->files)))
        {
          // start and close the file explicitly in case the file contains syntax errors
          $this->output->focus(current($this->files));
          $connector->connect(current($this->files));

          next($this->files);
        }

        if (!$connector->done())
        {
          $this->output->focus($connector->getConnectedFile());

          $connector->proceed();
          $done = false;

          if ($connector->done())
          {
            // start and close the file explicitly in case the file contains syntax errors
            $this->output->close();
          }
        }
      }
    }
    while (!$done);

    $this->output->flush();

    $planned = $this->output->getPlanned();
    $passed = $this->output->getPassed();
    $failed = $this->output->getFailed();
    $errors = $this->output->getErrors();
    $warnings = $this->output->getWarnings();

    return 0 == ($failed + $errors + $warnings) && $planned == $passed;
  }
}