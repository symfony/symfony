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
 * Represents an expected method invocation.
 *
 * Instances of this class are returned when you record a new method call on
 * a mock object while in record mode. You can then use this instance specify
 * further modifiers, like how often the method is expected to be called,
 * whether it is expected to be called with the same parameter types etc.
 *
 * The modifiers of this class support method chaining.
 *
 * <code>
 * $mock = LimeMock::create('MyClass', $output);
 * $mock->doSomething();
 * // returns LimeMockInvocationExpectation
 *
 * // let's use the returned object to configure the invocation
 * $mock->doSomething()->atLeastOnce()->returns('some value');
 * </code>
 *
 * You must inform this object of an invoked method by calling invoke(). When
 * that is done, you can use verify() to find out whether all the modifiers
 * succeeded, i.e. whether the method was called a sufficient number of times
 * etc. The results of the verification are then written to the output.
 *
 * Note: This class is implemented to verify a method automatically upon
 * invoking. If all the method modifiers are satisfied, the success message
 * is immediately printed to the output, even if you don't call verify(). If
 * you want to suppress all output, you should pass an instance of LimeOutputNone
 * to this class.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMockInvocationExpectation.php 23880 2009-11-14 10:14:34Z bschussek $
 */
class LimeMockInvocationExpectation
{
  protected
    $invocation         = null,
    $matched            = false,
    $output             = null,
    $countMatcher       = null,
    $parameterMatchers  = array(),
    $parameters         = array(),
    $withAnyParameters  = false,
    $returns            = false,
    $returnValue        = null,
    $exception          = null,
    $callback           = null,
    $strict             = false,
    $verified           = false;

  /**
   * Constructor.
   *
   * @param  LimeMockInvocation  $invocation  The expected method invocation
   * @param  LimeOutputInterface $output      The output to write at when
   *                                          verification passes or fails
   */
  public function __construct(LimeMockInvocation $invocation, LimeOutputInterface $output)
  {
    $this->invocation = $invocation;
    $this->output = $output;
  }

  protected function getMatchers()
  {
    return array_merge($this->parameterMatchers, ($this->countMatcher ? array($this->countMatcher) : array()));
  }

  /**
   * Returns the string representation.
   *
   * The string representation consists of the method name and the messages of
   * all applied modifiers.
   *
   * Example:
   *
   * "doSomething() was called at least once"
   *
   * @return string
   */
  public function __toString()
  {
    $string = $this->invocation.' was called';

    foreach ($this->getMatchers() as $matcher)
    {
      // avoid trailing spaces if the message is empty
      $string = rtrim($string.' '.$matcher->getMessage());
    }

    return $string;
  }

  /**
   * Notifies this object of the given method invocation.
   *
   * If any of the matchers decides that this method should not have been
   * invoked, an exception is thrown. If all matchers are satisfied, a success
   * message is printed to the output.
   *
   * If this object was configured to throw an exception, this exception is
   * thrown now. Otherwise the method's configured return value is returned.
   *
   * @param  LimeMockInvocation $invocation  The invoked method
   * @return mixed                           The configured return value
   *                                         See returns()
   * @throws LimeMockInvocationException     If the method should not have been
   *                                         invoked
   * @throws Exception                       If this object was configured to
   *                                         throw an exception
   *                                         See throw()
   */
  public function invoke(LimeMockInvocation $invocation)
  {
    try
    {
      foreach ($this->getMatchers() as $matcher)
      {
        $matcher->invoke($invocation);
      }
    }
    catch (LimeMockInvocationMatcherException $e)
    {
      throw new LimeMockInvocationException($this->invocation, $e->getMessage());
    }

    if (!$this->verified && $this->isSatisfied())
    {
      list ($file, $line) = LimeTrace::findCaller('LimeMockInterface');

      $this->output->pass((string)$this, $file, $line);

      $this->verified = true;
    }

    if (!is_null($this->callback))
    {
      $result = call_user_func_array($this->callback, $invocation->getParameters());

      return $this->returns ? $this->returnValue : $result;
    }

    if (!is_null($this->exception))
    {
      if (is_string($this->exception))
      {
        throw new $this->exception();
      }
      else
      {
        throw $this->exception;
      }
    }

    return $this->returnValue;
  }

  /**
   * Returns whether the method signature and parameters of this object match
   * the given invocation.
   *
   * @param  LimeMockInvocation $invocation
   * @return boolean
   */
  public function matches(LimeMockMethodInterface $method)
  {
    if ($this->invocation->getClass() != $method->getClass() || $this->invocation->getMethod() != $method->getMethod())
    {
      return false;
    }
    else if ($method instanceof LimeMockInvocation && !$this->withAnyParameters)
    {
      $index = 0;

      foreach ($this->parameterMatchers as $matcher)
      {
        $index = max($index, $matcher->getIndex());
      }

      return count($method->getParameters()) == $index;
    }
    else
    {
      return true;
    }
  }

  /**
   * Returns whether this object may be invoked.
   *
   * This method returns FALSE if the next call to invoke() would throw a
   * LimeMockInvocationException.
   *
   * @return boolean
   */
  public function isInvokable()
  {
    $result = true;

    foreach ($this->getMatchers() as $matcher)
    {
      $result = $result && $matcher->isInvokable();
    }

    return $result;
  }

  /**
   * Returns whether the requirements of all configured modifiers have been
   * fulfilled.
   *
   * @return boolean
   */
  public function isSatisfied()
  {
    $result = true;

    foreach ($this->getMatchers() as $matcher)
    {
      $result = $result && $matcher->isSatisfied();
    }

    return $result;
  }

  /**
   * Verifies whether the requirements of all configured modifiers have been
   * fulfilled.
   *
   * Depending on the result, either a failed or a passed test is written to the
   * output. A method may only be verified once.
   *
   * Note: Methods are verified automatically once invoke() is called and
   * all matchers are satisfied. In this case verify() simply does nothing.
   */
  public function verify()
  {
    if (!$this->verified)
    {
      list ($file, $line) = LimeTrace::findCaller('LimeMockInterface');

      if ($this->isSatisfied())
      {
        $this->output->pass((string)$this, $file, $line);
      }
      else
      {
        $this->output->fail((string)$this, $file, $line);
      }

      $this->verified = true;
    }
  }

  /**
   * This method is expected to be called the given number of times.
   *
   * @param  integer $times
   * @return LimeMockInvocationExpectation  This object
   */
  public function times($times)
  {
    $this->countMatcher = new LimeMockInvocationMatcherTimes($times);

    return $this;
  }

  /**
   * This method is expected to be called exactly once.
   *
   * @return LimeMockInvocationExpectation  This object
   */
  public function once()
  {
    return $this->times(1);
  }

  /**
   * This method is expected to be called never.
   *
   * @return LimeMockInvocationExpectation  This object
   */
  public function never()
  {
    return $this->times(0);
  }

  /**
   * This method is expected to be called zero times or more.
   *
   * @return LimeMockInvocationExpectation  This object
   */
  public function any()
  {
    $this->countMatcher = new LimeMockInvocationMatcherAny();

    return $this;
  }

  /**
   * This method is expected to be called once or more.
   *
   * @return LimeMockInvocationExpectation  This object
   */
  public function atLeastOnce()
  {
    $this->countMatcher = new LimeMockInvocationMatcherAtLeastOnce();

    return $this;
  }

  /**
   * This method is expected to be called any time within the given limits.
   *
   * The limits are inclusive. If the method is called exactly $start times,
   * the requirements of this modifier are fulfilled.
   *
   * @param  integer $start
   * @param  integer $end
   * @return LimeMockInvocationExpectation  This object
   */
  public function between($start, $end)
  {
    $this->countMatcher = new LimeMockInvocationMatcherBetween($start, $end);

    return $this;
  }

  /**
   * This method will return the given value when invoked.
   *
   * @param  mixed $value
   * @return LimeMockInvocationExpectation  This object
   */
  public function returns($value)
  {
    $this->returns = true;
    $this->returnValue = $value;

    return $this;
  }

  /**
   * This method will throw the given exception when invoked.
   *
   * @param  string|Exception $class
   * @return LimeMockInvocationExpectation  This object
   */
  public function throws($class)
  {
    $this->exception = $class;

    return $this;
  }

  /**
   * This method will call the given callback and return its return value when
   * invoked.
   *
   * @param  callable $callback
   * @return LimeMockInvocationExpectation  This object
   */
  public function callback($callback)
  {
    if (!is_callable($callback))
    {
      throw new InvalidArgumentException('The given argument is no callable');
    }

    $this->callback = $callback;

    return $this;
  }

  /**
   * This method must be called with the exact same parameter types.
   *
   * @return LimeMockInvocationExpectation  This object
   */
  public function strict()
  {
    $this->strict = true;

    if (!$this->withAnyParameters)
    {
      // reload matchers
      $this->withParameters($this->parameters);
    }

    return $this;
  }

  /**
   * Configures a parameter to match some constraint.
   *
   * The constraint can be configured on the returned matcher object.
   *
   * @param  integer $index  The index of the parameter. The first parameter has
   *                         index 1.
   * @return LimeMockInvocationMatcherParameter
   */
  public function parameter($index)
  {
    $this->parameterMatchers[$index] = $matcher = new LimeMockInvocationMatcherParameter($index, $this);

    return $matcher;
  }

  /**
   * This method can be called with any parameters.
   *
   * @return LimeMockInvocationExpectation  This object
   */
  public function withAnyParameters()
  {
    $this->parameterMatchers = array();
    $this->withAnyParameters = true;

    return $this;
  }

  /**
   * This method must be called with the given parameters.
   *
   * @param array $parameters
   * @param $strict
   * @return unknown_type
   */
  public function withParameters(array $parameters)
  {
    $this->parameters = $parameters;
    $this->parameterMatchers = array();
    $this->withAnyParameters = false;

    foreach ($parameters as $index => $value)
    {
      if ($this->strict)
      {
        $this->parameter($index+1)->same($value);
      }
      else
      {
        $this->parameter($index+1)->is($value);
      }
    }

    return $this;
  }
}