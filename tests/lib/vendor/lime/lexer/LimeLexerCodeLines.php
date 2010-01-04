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
 * Processes a source file for the lines of code and returns the line numbers.
 *
 * The following rules apply for detecting LOC and are conformant with
 * xdebug_get_code_coverage():
 *
 *  * identifier in function declaration == LOC
 *  * class declaration != LOC
 *  * method declaration != LOC
 *  * property declaration != LOC
 *  * } == LOC
 *  * { != LOC
 *  * { after class declaration == LOC
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @version    SVN: $Id: LimeLexerCodeLines.php 23701 2009-11-08 21:23:40Z bschussek $
 */
class LimeLexerCodeLines extends LimeLexer
{
  private
    $lines = array();

  /**
   * (non-PHPdoc)
   * @see lexer/LimeLexer#parse($content)
   */
  public function parse($content)
  {
    $this->lines = array();

    return parent::parse($content);
  }

  /**
   * (non-PHPdoc)
   * @see lexer/LimeLexer#process($text, $id)
   */
  protected function process($text, $id)
  {
    // whitespace is ignored
    if ($id == T_WHITESPACE)
    {
      return;
    }
    // PHP tags are ignored
    else if ($id == T_OPEN_TAG || $id == T_CLOSE_TAG)
    {
      return;
    }
    // class declarations are ignored
    else if ($this->inClassDeclaration())
    {
      return;
    }
    // function declarations are ignored, except for the identifier
    else if ($this->inFunctionDeclaration() && $id != T_STRING)
    {
      return;
    }
    // method declarations are ignored
    else if ($this->inClass() && $this->inFunctionDeclaration())
    {
      return;
    }
    // everything in classes except function body, the { and the } of the class is ignored
    else if ($this->inClass() && !$this->inFunction() && $text != '{' && $text != '}')
    {
      return;
    }
    // { is ignored, except for after class declarations
    else if ($text == '{' && !($this->inClass() && !$this->inFunction()))
    {
      return;
    }

    $this->lines[$this->getCurrentLine()] = true;
  }

  /**
   * (non-PHPdoc)
   * @see lexer/LimeLexer#getResult()
   */
  protected function getResult()
  {
    return array_keys($this->lines);
  }
}