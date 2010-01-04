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

/**
 * Extends lime_test to support annotations in test files.
 *
 * With this extension of lime_test, you can write very simple test files that
 * support more features than regular lime, such as code executed before
 * or after each test, code executed before or after the whole test suite
 * or expected exceptions.
 *
 * A test file can be written like this with LimeTest:
 *
 * <code>
 * <?php
 *
 * include dirname(__FILE__).'/../bootstrap/unit.php';
 *
 * $t = new LimeTest(2, new lime_output_color());
 *
 * // @Before
 * $r = new Record();
 *
 * // @Test
 * $r->setValue('Bumblebee');
 * $t->is($r->getValue(), 'Bumblebee', 'The setter works');
 *
 * // @Test
 * $t->is($r->getValue(), 'Foobar', 'The value is "Foobar" by default');
 * </code>
 *
 * The available annotations are:
 *
 *   * @BeforeAll  Executed before the whole test suite
 *   * @Before     Executed before each test
 *   * @After      Executed after each test
 *   * @AfterAll   Executed after the whole test suite
 *   * @Test       A test case
 *
 * You can add comments to the annotations that will be printed in the console:
 *
 * <code>
 * // @Test: The record supports setValue()
 * $r->setValue('Bumblebee')
 * // etc.
 * </code>
 *
 * You can also automatically test that certain exceptions are thrown from
 * within a test. To do that, you must call the method ->expect() on the
 * LimeTest object '''before''' executing the test that should throw
 * an exception.
 *
 * <code>
 * // @Test
 * $r->expect('RuntimeException');
 * throw new RuntimeException();
 *
 * // results in a passed test
 * </code>
 *
 * @package    lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeAnnotationSupport.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeAnnotationSupport
{
  protected static
    $enabled      = false;

  protected
    $originalPath = null,
    $path         = null,
    $test         = null,
    $lexer        = null;

  /**
   * Enables annotation support in a script file.
   */
  public static function enable()
  {
    // make sure that annotations are not replaced twice at the same time
    if (!self::$enabled)
    {
      self::$enabled = true;

      $support = new LimeAnnotationSupport(self::getScriptPath());
      $support->execute();

      exit;
    }
  }

  /**
   * Returns the file path of the executed test script
   *
   * @return string  The file path
   */
  protected static function getScriptPath()
  {
    $traces = debug_backtrace();
    $file = $traces[count($traces)-1]['file'];

    if (!is_file($file))
    {
      throw new RuntimeException('The script name from the traces is not valid: '.$file);
    }

    return $file;
  }

  /**
   * Constructor.
   *
   * Creates a backup of the given file with the extension .bak.
   */
  protected function __construct($path)
  {
    $this->originalPath = $path;
    $this->path = dirname($path).'/@'.basename($path);

    register_shutdown_function(array($this, 'cleanup'));
  }

  /**
   * Removes the transformed script file.
   */
  public function cleanup()
  {
    if (file_exists($this->path))
    {
      unlink($this->path);
    }
  }

  /**
   * Transforms the annotations in the script file and executes the resulting
   * script.
   */
  protected function execute()
  {
    if (file_exists($this->path))
    {
      unlink($this->path);
    }

    $this->lexer = new LimeLexerTransformAnnotations($this->path);
    $callbacks = $this->lexer->parse($this->originalPath);

    $this->includeTestFile();

    $testRunner = new LimeTestRunner($this->test ? $this->test->getOutput() : null);

    foreach ($callbacks as $annotation => $callbacks)
    {
      $addMethod = 'add'.$annotation;
      foreach ($callbacks as $list)
      {
        list ($callback, $comment) = $list;
        $testRunner->$addMethod($callback, $comment);
      }
    }

    if ($this->test instanceof LimeTest)
    {
      $testRunner->addExceptionHandler(array($this->test, 'handleException'));
      $testRunner->addAfter(array($this->test, 'verifyException'));
    }

    $testRunner->run();
  }

  /**
   * Includes the test file in a separate scope.
   *
   * @param string $testVariable
   */
  protected function includeTestFile()
  {
//    var_dump(file_get_contents($this->path));
    include $this->path;

    if (!is_null($this->lexer->getTestVariable()))
    {
      eval(sprintf('$this->test = %s;', $this->lexer->getTestVariable()));
    }
  }
}