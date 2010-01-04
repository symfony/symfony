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
 * Formats test results as multidimensional array.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeOutputArray.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeOutputArray implements LimeOutputInterface
{
  protected
    $serialize = false,
    $results = array(),
    $currentResults = null;

  /**
   * Constructor.
   *
   * @param boolean $serialize  Whether the array should be serialized before printing
   */
  public function __construct($serialize = false)
  {
    $this->serialize = $serialize;
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#supportsThreading()
   */
  public function supportsThreading()
  {
    return true;
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#focus($file)
   */
  public function focus($file)
  {
    $this->currentResults =& $this->getResults($file);
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#close()
   */
  public function close()
  {
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#plan($amount)
   */
  public function plan($amount)
  {
    $this->currentResults['stats']['plan'] = $amount;
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#pass($message, $file, $line)
   */
  public function pass($message, $file, $line)
  {
    $this->currentResults['stats']['total']++;
    $this->currentResults['stats']['passed'][] = $this->addTest(true, $line, $file, $message);
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#fail($message, $file, $line, $error)
   */
  public function fail($message, $file, $line, $error = null)
  {
    $index = $this->addTest(false, $line, $file, $message);

    $this->currentResults['stats']['total']++;
    $this->currentResults['stats']['failed'][] = $index;

    if (!is_null($error))
    {
      $this->currentResults['tests'][$index]['error'] = $error;
    }
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#skip($message, $file, $line)
   */
  public function skip($message, $file, $line)
  {
    $this->currentResults['stats']['total']++;
    $this->currentResults['stats']['skipped'][] = $this->addTest(true, $line, $file, $message);
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#todo($message, $file, $line)
   */
  public function todo($message, $file, $line)
  {
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#warning($message, $file, $line)
   */
  public function warning($message, $file, $line)
  {
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#error($error)
   */
  public function error(LimeError $error)
  {
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#comment($message)
   */
  public function comment($message)
  {
  }

  /**
   * (non-PHPdoc)
   * @see output/LimeOutputInterface#flush()
   */
  public function flush()
  {
    if ($this->serialize)
    {
      print serialize($this->results);
    }
    else
    {
      var_export($this->results);
    }
  }

  /**
   * Returns the results as array.
   *
   * @return array
   */
  public function toArray()
  {
    return $this->results;
  }

  /**
   * Returns the result array of the given test file.
   *
   * @param  string $file
   * @return array
   */
  protected function &getResults($file)
  {
    foreach ($this->results as $key => &$fileResults)
    {
      if ($fileResults['file'] == $file)
      {
        return $fileResults;
      }
    }

    $newResults = array(
      'file' => $file,
      'tests' => array(),
      'stats' => array(
        'plan' => 0,
        'total' => 0,
        'failed' => array(),
        'passed' => array(),
        'skipped' => array(),
      ),
    );

    $this->results[] =& $newResults;

    return $newResults;
  }

  /**
   * Addsthe given test to the test results.
   *
   * @param  boolean $status
   * @param  integer $line
   * @param  string  $file
   * @param  string  $message
   * @return integer
   */
  protected function addTest($status, $line, $file, $message)
  {
    $index = count($this->currentResults['tests']) + 1;

    $this->currentResults['tests'][$index] = array(
      'line' => $line,
      'file' => $file,
      'message' => $message,
      'status' => $status,
    );

    return $index;
  }
}