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
 * Stores an error and optionally its trace.
 *
 * This class is similar to PHP's native Exception class, but is guaranteed
 * to be serializable. The native Exception class is not serializable if the
 * traces contain circular references between objects.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeError.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeError implements Serializable
{
  private
    $type    = null,
    $message = null,
    $file    = null,
    $line    = null,
    $trace   = null;

  /**
   * Creates a new instance and copies the data from an exception.
   *
   * @param  Exception $exception
   * @return LimeError
   */
  public static function fromException(Exception $exception)
  {
    return new self(
      $exception->getMessage(),
      $exception->getFile(),
      $exception->getLine(),
      get_class($exception),
      $exception->getTrace()
    );
  }

  /**
   * Constructor.
   *
   * @param string  $message  The error message
   * @param string  $file     The file where the error occurred
   * @param integer $line     The line where the error occurred
   * @param string  $type     The error type, f.i. "Fatal Error"
   * @param array   $trace    The traces of the error
   */
  public function __construct($message, $file, $line, $type = 'Error', array $trace = array())
  {
    $this->message = $message;
    $this->file = $file;
    $this->line = $line;
    $this->type = $type;
    $this->trace = $trace;
  }

  /**
   * Returns the error type.
   *
   * @return string
   */
  public function getType()
  {
    return $this->type;
  }

  /**
   * Returns the error message.
   *
   * @return string
   */
  public function getMessage()
  {
    return $this->message;
  }

  /**
   * Returns the file where the error occurred.
   *
   * @return string
   */
  public function getFile()
  {
    return $this->file;
  }

  /**
   * Returns the line where the error occurred.
   *
   * @return integer
   */
  public function getLine()
  {
    return $this->line;
  }

  /**
   * Returns the trace of the error.
   *
   * @return array
   */
  public function getTrace()
  {
    return $this->trace;
  }

  /**
   * Serializes the error.
   *
   * @see    Serializable#serialize()
   * @return string   The serialized error content
   */
  public function serialize()
  {
    $traces = $this->trace;

    foreach ($traces as &$trace)
    {
      if (array_key_exists('args', $trace))
      {
        foreach ($trace['args'] as &$value)
        {
          // TODO: This should be improved. Maybe we can check for recursions
          // and only exclude duplicate objects from the trace
          if (is_object($value))
          {
            // replace object by class name
            $value = sprintf('object (%s) (...)', get_class($value));
          }
          else if (is_array($value))
          {
            $value = 'array(...)';
          }
        }
      }
    }

    return serialize(array($this->file, $this->line, $this->message, $traces, $this->type));
  }

  /**
   * Unserializes an error.
   *
   * @see   Serializable#unserialize()
   * @param string $data  The serialized error content
   */
  public function unserialize($data)
  {
    list($this->file, $this->line, $this->message, $this->trace, $this->type) = unserialize($data);
  }
}