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
 * Extracts all global variables from a source file.
 *
 * This lexer includes all global variables that are not inside annotations,
 * except variables from the scope of the annotations passed to the constructor,
 * which are included as well.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeLexerVariables.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeLexerVariables extends LimeLexerAnnotationAware
{
  protected
    $includedAnnotations = array(),
    $variables = array();

  /**
   * Constructor.
   *
   * @param  array $allowedAnnotations   The list of allowed annotation names
   * @param  array $includedAnnotations  The list of annotation names whose
   *                                     variables are considered global
   */
  public function __construct(array $allowedAnnotations = array(), array $includedAnnotations = array())
  {
    parent::__construct($allowedAnnotations);

    $this->includedAnnotations = $includedAnnotations;
  }

  /**
   * (non-PHPdoc)
   * @see LimeLexer#process($text, $id)
   */
  protected function process($text, $id)
  {
    if ($id == T_VARIABLE && !$this->inClass() && !$this->inFunction()
        && (!$this->inAnnotation() || in_array($this->getCurrentAnnotation(), $this->includedAnnotations)))
    {
      $this->variables[] = $text;
    }
  }

  /**
   * (non-PHPdoc)
   * @see LimeLexer#getResult()
   */
  protected function getResult()
  {
    return array_unique($this->variables);
  }
}