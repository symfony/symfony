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
 * Generates mock objects
 *
 * This class generates configurable mock objects based on existing interfaces,
 * classes or virtual (non-existing) class names. You can use it to create
 * objects of classes that you have not implemented yet, or to substitute
 * an existing class in a test.
 *
 * A mock object is created with the create() method:
 *
 * <code>
 * $mock = LimeMock::create('MyClass', $output);
 * </code>
 *
 * Note: The LimeTest class offers an easy access to preconfigured mocks and
 * stubs using the methods mock() and stub().
 *
 * Initially the mock is in recording mode. In this mode you just make the
 * expected method calls with the expected parameters. You can use modifiers
 * to configure return values or exceptions that should be thrown.
 *
 * <code>
 * // method "someMethod()" returns "return value" when called with "parameter"
 * $mock->someMethod('parameter')->returns('return value');
 * </code>
 *
 * You can find the complete list of method modifiers in class
 * LimeMockInvocationExpectation. By default, expected methods are initialized
 * with the modifier once(). If the option "nice" is set, the method is
 * initialized with the modifier any() instead.
 *
 * Once the recording is over, you must call the method replay() on the mock.
 * After the call to this method, the mock is in replay mode. In this mode, it
 * listens for method calls and returns the results configured before.
 *
 * <code>
 * $mock = LimeMock::create('MyClass', $output);
 * $mock->add(1, 2)->returns(3);
 * $mock->replay();
 *
 * echo $mock->add(1, 2);
 * // returns 3
 * </code>
 *
 * You also have the possibility to find out whether all the configured
 * methods have been called with the right parameters while in replay mode
 * by calling verify().
 *
 * <code>
 * $mock = LimeMock::create('MyClass', $output);
 * $mock->add(1,2);
 * $mock->replay();
 * $mock->add(1);
 * $mock->verify();
 *
 * // results in a failing test
 * </code>
 *
 * The method create() accepts several options to configure the created mock:
 *
 *    * strict:             If set to TRUE, the mock expects methods to be
 *                          called in the same order in which they were recorded.
 *                          Additionally, method parameters will be compared
 *                          with strict typing. Default: FALSE
 *    * generate_controls:  If set to FALSE, the mock's control methods
 *                          replay(), verify() etc. will not be generated.
 *                          Setting this option is useful when the mocked
 *                          class contains any of these methods. You then have
 *                          to access the control methods statically in this
 *                          class, f.i. LimeMock::replay($mock);
 *                          Default: TRUE
 *    * stub_methods:       If set to FALSE, method implementations in the
 *                          mocked class are called when a method is not
 *                          configured to be stubbed. Default: TRUE
 *    * nice:               See LimeMockBehaviour
 *    * no_exceptions:      See LimeMockBehaviour
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeMock.php 24994 2009-12-06 21:02:45Z bschussek $
 * @see        LimeMockBehaviour
 * @see        LimeMockInvocationExpectation
 */
class LimeMock
{
  protected static
    $methodTemplate               = '%s function %s(%s) { $args = func_get_args(); return $this->__call(\'%s\', $args); }',
    $parameterTemplate            = '%s %s',
    $parameterWithDefaultTemplate = '%s %s = %s',

    $illegalMethods = array(
      '__construct',
      '__call',
      '__lime_replay',
      '__lime_getState',
    ),

    $controlMethods = array(
      'replay',
      'any',
      'reset',
      'verify',
      'setExpectNothing',
    );

  /**
   * Creates a new mock object for the given class or interface name.
   *
   * The class/interface does not necessarily have to exist. Every generated
   * object fulfills the condition ($mock instanceof $class).
   *
   * @param  string $classOrInterface     The (non-)existing class/interface
   *                                      you want to mock
   * @param  LimeOutputInterface $output  The output for displaying the test results
   * @param  array $options               Generation options. See the class
   *                                      description for more information.
   * @return LimeMockInterface            The mock object
   */
  public static function create($classOrInterface, LimeOutputInterface $output, array $options = array())
  {
    $options = array_merge(array(
      'strict'              =>  false,
      'generate_controls'   =>  true,
      'stub_methods'        =>  true,
    ), $options);

    if ($options['strict'])
    {
      $behaviour = new LimeMockOrderedBehaviour($options);
    }
    else
    {
      $behaviour = new LimeMockUnorderedBehaviour($options);
    }

    $name = self::generateClass($classOrInterface, $options['generate_controls']);

    return new $name($classOrInterface, $behaviour, $output, $options['stub_methods']);
  }

  /**
   * Generates a mock class for the given class/interface name and returns
   * the generated class name.
   *
   * @param  string  $classOrInterface  The mocked class/interface name
   * @param  boolean $generateControls  Whether control methods should be generated.
   * @return string                     The generated class name
   */
  protected static function generateClass($classOrInterface, $generateControls = true)
  {
    $methods = '';

    if (!class_exists($classOrInterface, false) && !interface_exists($classOrInterface, false))
    {
      if (($pos = strpos($classOrInterface, '\\')) !== false)
      {
        $namespace = substr($classOrInterface, 0, $pos);
        $interface = substr($classOrInterface, $pos+1);

        eval(sprintf('namespace %s { interface %s {} }', $namespace, $interface));
      }
      else
      {
        eval(sprintf('interface %s {}', $classOrInterface));
      }
    }

    $class = new ReflectionClass($classOrInterface);
    foreach ($class->getMethods() as $method)
    {
      /* @var $method ReflectionMethod */
      if (in_array($method->getName(), self::$controlMethods) && $generateControls)
      {
        throw new LogicException(sprintf('The mocked class "%s" contains the method "%s", which conflicts with the mock\'s control methods. Please set the option "generate_controls" to false.', $classOrInterface, $method->getName()));
      }

      if (!in_array($method->getName(), self::$illegalMethods) && !$method->isFinal())
      {
        $modifiers = Reflection::getModifierNames($method->getModifiers());
        $modifiers = array_diff($modifiers, array('abstract'));
        $modifiers = implode(' ', $modifiers);

        $parameters = array();

        foreach ($method->getParameters() as $parameter)
        {
          $typeHint = '';

          /* @var $parameter ReflectionParameter */
          if ($parameter->getClass())
          {
            $typeHint = $parameter->getClass()->getName();
          }
          else if ($parameter->isArray())
          {
            $typeHint = 'array';
          }

          $name = '$'.$parameter->getName();

          if ($parameter->isPassedByReference())
          {
            $name = '&'.$name;
          }

          if ($parameter->isOptional())
          {
            $default = var_export($parameter->getDefaultValue(), true);
            $parameters[] = sprintf(self::$parameterWithDefaultTemplate, $typeHint, $name, $default);
          }
          else
          {
            $parameters[] = sprintf(self::$parameterTemplate, $typeHint, $name);
          }
        }

        $methods .= sprintf(self::$methodTemplate, $modifiers, $method->getName(),
            implode(', ', $parameters), $method->getName())."\n  ";
      }
    }

    $interfaces = array();

    $name = self::generateName($class->getName());

    $declaration = 'class '.$name;

    if ($class->isInterface())
    {
      $interfaces[] = $class->getName();
    }
    else
    {
      $declaration .= ' extends '.$class->getName();
    }

    $interfaces[] = 'LimeMockInterface';

    if (count($interfaces) > 0)
    {
      $declaration .= ' implements '.implode(', ', $interfaces);
    }

    $template = new LimeMockTemplate(dirname(__FILE__).'/template/mocked_class.tpl');

    eval($template->render(array(
      'class_declaration'   =>  $declaration,
      'methods'             =>  $methods,
      'generate_controls'   =>  $generateControls,
    )));

    return $name;
  }

  /**
   * Generates a mock class name for the given original class/interface name.
   *
   * @param  string $originalName
   * @return string
   */
  protected static function generateName($originalName)
  {
    // strip namespace separators
    $originalName = str_replace('\\', '_', $originalName);

    while (!isset($name) || class_exists($name, false))
    {
      // inspired by PHPUnit_Framework_MockObject_Generator
      $name = 'Mock_'.$originalName.'_'.substr(md5(microtime()), 0, 8);
    }

    return $name;
  }

  /**
   * Turns the given mock into replay mode.
   *
   * @param LimeMockInterface $mock
   */
  public static function replay(LimeMockInterface $mock)
  {
    return $mock->__lime_replay();
  }

  /**
   * Resets the given mock.
   *
   * All expected invocations are removed, the mock is set to record mode again.
   *
   * @param LimeMockInterface $mock
   */
  public static function reset(LimeMockInterface $mock)
  {
    return $mock->__lime_reset();
  }

  /**
   * Expects the given method on the given mock to be called with any parameters.
   *
   * The LimeMockInvocationExpectation object is returned and allows you to
   * set further modifiers on the method expectation.
   *
   * @param  LimeMockInterface $mock
   * @param  string            $methodName
   * @return LimeMockInvocationExpectation
   */
  public static function any(LimeMockInterface $mock, $methodName)
  {
    return $mock->__call($methodName, null);
  }

  /**
   * Configures the mock to expect no method call.
   *
   * @param  LimeMockInterface $mock
   */
  public static function setExpectNothing(LimeMockInterface $mock)
  {
    return $mock->__lime_getState()->setExpectNothing();
  }

  /**
   * Verifies the given mock.
   *
   * @param  LimeMockInterface $mock
   */
  public static function verify(LimeMockInterface $mock)
  {
    return $mock->__lime_getState()->verify();
  }
}


