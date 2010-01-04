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
 * Analyzes PHP scripts taking annotations into account.
 *
 * Like LimeLexer, this class analyzes PHP scripts syntactically but is aware
 * of annotations. Annotations are expected to be expressed using single
 * line comments. Optionally, you can add a comment to the annotation, which
 * needs to be separated from the annotation by any number of colons or spaces.
 *
 * <code>
 * // @Annotation: Optional comment
 * </code>
 *
 * You can extend this class if you want to write your own lexer that takes
 * existing annotations into account. You have to pass a number of
 * expected annotations to the constructor. Any other exception that is not
 * passed in this array will result in an exception during parsing.
 *
 * <code>
 * $lexer = new CustomLexerAnnotationAware(array('Annotation1', 'Annotation2'));
 * </code>
 *
 * The following script will lead to an error when parsed:
 *
 * <code>
 * $i = 1;
 * // @Annotation3
 * $i++;
 * </code>
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeLexerAnnotationAware.php 23701 2009-11-08 21:23:40Z bschussek $
 * @see        LimeLexer
 */
abstract class LimeLexerAnnotationAware extends LimeLexer
{
  private
    $allowedAnnotations,
    $currentAnnotation,
    $currentAnnotationComment,
    $inAnnotation,
    $inAnnotationDeclaration;

  /**
   * Constructor.
   *
   * Accepts an array of expected annotation names as argument. Any annotation
   * that is not listed in this array will cause an exception during parsing.
   *
   * @param  array $allowedAnnotations  The list of allowed annotations.
   */
  public function __construct(array $allowedAnnotations = array())
  {
    $this->allowedAnnotations = $allowedAnnotations;
  }

  /**
   * (non-PHPdoc)
   * @see lexer/LimeLexer#parse($content)
   */
  public function parse($content)
  {
    $this->currentAnnotation = null;
    $this->currentAnnotationComment = null;
    $this->inAnnotation = false;
    $this->inAnnotationDeclaration = false;

    return parent::parse($content);
  }

  /**
   * (non-PHPdoc)
   * @see lexer/LimeLexer#beforeProcess($text, $id)
   */
  protected function beforeProcess($text, $id)
  {
    if (!$this->inClass() && !$this->inFunction() && $id = T_COMMENT && strpos($text, '//') === 0)
    {
      list($annotation, $comment) = $this->extractAnnotation($text);

      if (!is_null($annotation))
      {
        $this->currentAnnotation = $annotation;
        $this->currentAnnotationComment = $comment;
        $this->inAnnotation = true;
        $this->inAnnotationDeclaration = true;
      }
    }
    else
    {
      $this->inAnnotationDeclaration = false;
    }
  }

  /**
   * Returns whether the parser currently is within any annotation.
   *
   * All the code following an annotation declaration is considered to be
   * inside this annotation's block. In annotated script, this method will thus
   * only return false before the first annotation declaration.
   *
   * @return boolean  TRUE if any annotation declaration preceded the current
   *                  position of the lexer
   */
  protected function inAnnotation()
  {
    return $this->inAnnotation;
  }

  /**
   * Returns whether the parser is currently inside an annotation declaration.
   *
   * An annotation declaration is any single line comment with a word that
   * starts with "@" and any optional following comments. Annotations and
   * comments have to be separated by one or more spaces or colons.
   *
   * <code>
   * // @Annotation: Optional comment
   * </code>
   *
   * @return boolean
   */
  protected function inAnnotationDeclaration()
  {
    return $this->inAnnotationDeclaration;
  }

  /**
   * Returns the name of the currently active annotation.
   *
   * @return boolean
   * @see    inAnnotation()
   */
  protected function getCurrentAnnotation()
  {
    return $this->currentAnnotation;
  }

  /**
   * Returns the comment of the currently active annotation.
   *
   * @return boolean
   * @see    inAnnotation()
   */
  protected function getCurrentAnnotationComment()
  {
    return $this->currentAnnotationComment;
  }

  /**
   * Returns the array of allowed annotation names.
   *
   * This array can be set in the constructor.
   *
   * @return array
   */
  protected function getAllowedAnnotations()
  {
    return $this->allowedAnnotations;
  }

  /**
   * Extracts an annotation from a single-line comment and validates it.
   *
   * Possible valid annotations are:
   * <code>
   * // @Annotation
   * // @Annotation: Some comment here
   * </code>
   *
   * The results for those annotations are:
   * <code>
   * array('Annotation', null);
   * array('Annotation', 'Some comment here');
   * </code>
   *
   * @param  string $text  Some code
   *
   * @return array         An array with the annotation name and the annotation
   *                       comment. If either of both cannot be read, it is NULL.
   */
  protected function extractAnnotation($text)
  {
    if (preg_match('/^\/\/\s*@(\w+)([:\s]+(.*))?\s*$/', $text, $matches))
    {
      $annotation = $matches[1];
      $data = count($matches) > 3 ? trim($matches[3]) : null;

      if (!in_array($annotation, $this->allowedAnnotations))
      {
        throw new LogicException(sprintf('The annotation "%s" is not valid', $annotation));
      }

      return array($annotation, $data);
    }
    else
    {
      return array(null, null);
    }
  }
}