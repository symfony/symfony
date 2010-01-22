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

class LimeOutputXml implements LimeOutputInterface
{
  protected
    $output = null;

  public function __construct()
  {
    $this->output = new LimeOutputArray();
  }

  public function supportsThreading()
  {
    return $this->output->supportsThreading();
  }

  public function focus($file)
  {
    return $this->output->focus($file);
  }

  public function close()
  {
    return $this->output->close();
  }

  public function plan($amount)
  {
    return $this->output->plan($amount);
  }

  public function pass($message, $file, $line)
  {
    return $this->output->plan($message, $file, $line);
  }

  public function fail($message, $file, $line, $error = null)
  {
    return $this->output->fail($message, $file, $line, $error);
  }

  public function skip($message, $file, $line)
  {
    return $this->output->skip($message, $file, $line);
  }

  public function todo($message, $file, $line)
  {
    return $this->output->todo($message, $file, $line);
  }

  public function warning($message, $file, $line)
  {
    return $this->output->warning($message, $file, $line);
  }

  public function error(LimeError $error)
  {
    return $this->output->error($error);
  }

  public function comment($message)
  {
    return $this->output->comment($message);
  }

  public function flush()
  {
    print $this->toXml();
  }

  public function toXml()
  {
    $results = $this->output->toArray();

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->appendChild($testsuites = $dom->createElement('testsuites'));

    $errors = 0;
    $failures = 0;
    $errors = 0;
    $skipped = 0;
    $assertions = 0;

    foreach ($results as $result)
    {
      $testsuites->appendChild($testSuite = $dom->createElement('testsuite'));
      $testSuite->setAttribute('name', basename($result['file'], '.php'));
      $testSuite->setAttribute('file', $result['file']);
      $testSuite->setAttribute('failures', count($result['stats']['failed']));
      $testSuite->setAttribute('errors', 0);
      $testSuite->setAttribute('skipped', count($result['stats']['skipped']));
      $testSuite->setAttribute('tests', $result['stats']['plan']);
      $testSuite->setAttribute('assertions', $result['stats']['plan']);

      $failures += count($result['stats']['failed']);
      $skipped += count($result['stats']['skipped']);
      $assertions += $result['stats']['plan'];

      foreach ($result['tests'] as $test)
      {
        $testSuite->appendChild($testCase = $dom->createElement('testcase'));
        $testCase->setAttribute('name', $test['message']);
        $testCase->setAttribute('file', $test['file']);
        $testCase->setAttribute('line', $test['line']);
        $testCase->setAttribute('assertions', 1);
        if (!$test['status'])
        {
          $testCase->appendChild($failure = $dom->createElement('failure'));
          $failure->setAttribute('type', 'lime');
          if (array_key_exists('error', $test))
          {
            $failure->appendChild($dom->createTextNode($test['error']));
          }
        }
      }
    }

    $testsuites->setAttribute('failures', $failures);
    $testsuites->setAttribute('errors', $errors);
    $testsuites->setAttribute('tests', $assertions);
    $testsuites->setAttribute('assertions', $assertions);
    $testsuites->setAttribute('skipped', $skipped);

    return $dom->saveXml();
  }
}