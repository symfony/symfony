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
 * Transforms annotated code in a file into functions.
 *
 * The created function names are returned by the function parse(), indexed
 * by annotation name.
 *
 * <code>
 * $lexer = new LimeLexerTransformAnnotations('path/to/transformed/file.php', array('First', 'Second'));
 * $functions = $lexer->parse('/path/to/original/file.php');
 *
 * // => array('First' => array(...), 'Second' => array(...))
 * </code>
 *
 * The annotated source file for the above code could look like this:
 *
 * <code>
 * $test = 'nothing';
 *
 * // @First
 * $test = 'First';
 *
 * // @Second
 * $test = 'Second';
 *
 * // @First
 * echo $test;
 * </code>
 *
 * You can include the transformed file and execute a certain subset of
 * annotations:
 *
 * <code>
 * include 'path/to/transformed/file.php';
 *
 * foreach ($functions['First'] as $function)
 * {
 *   $function();
 * }
 *
 * // => First
 * </code>
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeLexerTransformAnnotations.php 25934 2009-12-27 20:44:07Z bschussek $
 * @see        LimeLexerAnnotationAware
 */
class LimeLexerTransformAnnotations extends LimeLexerAnnotationAware
{
  protected static
    $annotations  = array('Test', 'Before', 'After', 'BeforeAll', 'AfterAll');

  protected
    $fileName,
    $file,
    $variables,
    $functions,
    $functionCount,
    $initialized,
    $testVariable,
    $classBuffer,
    $classNotLoaded,
    $firstAnnotation;

  /**
   * Constructor.
   *
   * @param  string $targetFile          The file where the transformed code
   *                                     will be written.
   * @param  array  $allowedAnnotations  The allowed annotations.
   */
  public function __construct($targetFile)
  {
    parent::__construct(self::$annotations);

    $this->fileName = $targetFile;
  }

  /**
   * Transforms the annoated code in the given file and writes it to the
   * target file.
   *
   * @see LimeLexer#parse($content)
   */
  public function parse($content)
  {
    if (is_readable($content))
    {
      $content = file_get_contents($content);
    }

    $lexer = new LimeLexerVariables($this->getAllowedAnnotations(), array('Before'));
    $this->variables = $lexer->parse($content);

    $lexer = new LimeLexerTestVariable();
    $this->testVariable = $lexer->parse($content);

    $this->initialized = false;
    $this->functionCount = 0;
    $this->functions = array();
    $this->classBuffer = '';
    $this->classNotLoaded = false;
    $this->firstAnnotation = true;

    foreach ($this->getAllowedAnnotations() as $annotation)
    {
      $this->functions[$annotation] = array();
    }

    // backup the contents for the case that the path == filename
    $this->file = fopen($this->fileName, 'w');

    $result = parent::parse($content);

    if ($this->inAnnotation())
    {
      fwrite($this->file, "\n}");
    }

    fclose($this->file);

    return $result;
  }

  /**
   * Returns the name of the first global variable that contains an instance
   * of LimeTest or any subclass.
   *
   * If no such variable could be detected, NULL is returned.
   *
   * @return string
   */
  public function getTestVariable()
  {
    return $this->testVariable;
  }

  /**
   * (non-PHPdoc)
   * @see LimeLexer#process($text, $id)
   */
  protected function process($text, $id)
  {
    if (!$this->inClassDeclaration())
    {
      $this->classBuffer = '';
    }

    if (!$this->inClass())
    {
      $this->classNotLoaded = false;
    }

    // Some classes are automatically loaded when the script is opened, others
    // are not. These other classes need to be left in the source code,
    // otherwise they cannot be instantiated later.
    // This functionality is covered in LimeAnnotationSupportTest 11+12
    if ($this->inClassDeclaration())
    {
      if ($this->getCurrentClass() && !class_exists($this->getCurrentClass()) && !interface_exists($this->getCurrentClass()))
      {
        $this->classNotLoaded = true;
        $text = $this->classBuffer.$text;
        $this->classBuffer = '';
      }
      else
      {
        $this->classBuffer .= $text;
      }
    }

    // Closures and anonymous functions should not be stripped from the output
    if ($this->inFunction())
    {
      if ($this->inFunctionDeclaration())
      {
        $this->functionBuffer .= $text;
        $text = '';
      }
      // if the name of the function is NULL, it is a closure/anonymous function
      else if (!$this->getCurrentFunction() || $this->inClass())
      {
        $text = $this->functionBuffer.$text;
        $this->functionBuffer = '';
      }
      else
      {
        $text = str_repeat("\n", count(explode("\n", $this->functionBuffer.$text)) - 1);
        $this->functionBuffer = '';
      }
    }

    if ($id == T_OPEN_TAG && !$this->initialized)
    {
      if (count($this->variables))
      {
        $text .= 'global '.implode(', ', $this->variables).';';
      }
      $this->initialized = true;
    }
    else if ($this->inClass() && !$this->classNotLoaded)
    {
      $text = str_repeat("\n", count(explode("\n", $text)) - 1);
    }
    else if ($this->inAnnotationDeclaration())
    {
      $functionName = '__lime_annotation_'.($this->functionCount++);
      $this->functions[$this->getCurrentAnnotation()][] = array($functionName, $this->getCurrentAnnotationComment());

      $text = $this->firstAnnotation ? '' : '} ';
      $this->firstAnnotation = false;
      $variables = count($this->variables) ? sprintf('global %s;', implode(', ', $this->variables)) : '';
      $text .= sprintf("function %s() { %s\n", $functionName, $variables);
    }

    fwrite($this->file, $text);
  }

  /**
   * (non-PHPdoc)
   * @see LimeLexer#getResult()
   */
  protected function getResult()
  {
    return $this->functions;
  }
}