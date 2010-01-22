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
 * Analyzes PHP scripts syntactically.
 *
 * You can extend this class if you want to write your own lexer that parses
 * a PHP file for specific information.
 *
 * To create your own lexer, implement the methods process() and getResult()
 * in your class. process() is called for every token in the file. You can use
 * the methods of this class to retrieve more information about the context of
 * the token, f.i. whether the token is inside a class or function etc.
 *
 * The method getResult() must return the value that should be returned by
 * parse().
 *
 * A lexer is stateless. This means that you can analyze any number of PHP
 * scripts with the same lexer instance.
 *
 * @package    Lime
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id: LimeLexer.php 25934 2009-12-27 20:44:07Z bschussek $
 */
abstract class LimeLexer
{
  private
    $continue,
    $currentClass,
    $inClassDeclaration,
    $currentFunction,
    $inFunctionDeclaration,
    $inAssignment,
    $endOfCurrentExpr,
    $currentLine;

  /**
   * Analyzes the given file or PHP code.
   *
   * @param  string $content  A file path or a string with PHP code.
   *
   * @return mixed            The result from getResult()
   */
  public function parse($content)
  {
    if (is_readable($content))
    {
      $content = file_get_contents($content);
    }

    $this->continue = true;
    $this->currentClass = array();
    $this->inClassDeclaration = false;
    $this->currentFunction = array();
    $this->inFunctionDeclaration = false;
    $this->inAssignment = false;
    $this->endOfCurrentExpr = true;
    $this->currentLine = 1;

    $tokens = token_get_all($content);
    $openBraces = 0;
    foreach ($tokens as $token)
    {
      if (is_string($token))
      {
        switch ($token)
        {
          case '{':
            ++$openBraces;
            $this->inClassDeclaration = false;
            $this->inFunctionDeclaration = false;
            break;
          case ';':
            // abstract functions
            if ($this->inFunctionDeclaration)
            {
              $this->inFunctionDeclaration = false;
              unset($this->currentFunction[$openBraces]);
            }
            $this->endOfCurrentExpr = true;
            break;
          case '}':
            $this->endOfCurrentExpr = true;
            break;
          case '=':
            $this->endOfCurrentExpr = false;
            $this->inAssignment = true;
            break;
        }


        if ($this->endOfCurrentExpr)
        {
          $this->inAssignment = false;
        }

        $this->beforeProcess($token, null);
        $this->process($token, null);
        $this->afterProcess($token, null);

        switch ($token)
        {
          case '}':
            --$openBraces;
            if (array_key_exists($openBraces, $this->currentClass))
            {
              unset($this->currentClass[$openBraces]);
            }
            if (array_key_exists($openBraces, $this->currentFunction))
            {
              unset($this->currentFunction[$openBraces]);
            }
            break;
        }
      }
      else
      {
        list($id, $text) = $token;

        switch ($id)
        {
          case T_CURLY_OPEN:
          case T_DOLLAR_OPEN_CURLY_BRACES:
            ++$openBraces;
            break;
          case T_OPEN_TAG:
          case T_CLOSE_TAG:
            $this->endOfCurrentExpr = true;
            $this->currentLine += count(explode("\n", $text)) - 1;
            break;
          case T_WHITESPACE:
          case T_START_HEREDOC:
          case T_CONSTANT_ENCAPSED_STRING:
          case T_ENCAPSED_AND_WHITESPACE:
          case T_COMMENT:
          case T_DOC_COMMENT:
            $this->currentLine += count(explode("\n", $text)) - 1;
            break;
          case T_ABSTRACT:
            if ($this->inClass())
            {
              $this->currentFunction[$openBraces] = null;
              $this->inFunctionDeclaration = true;
            }
            else
            {
              $this->currentClass[$openBraces] = null;
              $this->inClassDeclaration = true;
            }
            break;
          case T_INTERFACE:
          case T_CLASS:
            $this->currentClass[$openBraces] = null;
            $this->inClassDeclaration = true;
            break;
          case T_FUNCTION:
            $this->currentFunction[$openBraces] = null;
            $this->inFunctionDeclaration = true;
            break;
          case T_STRING:
            if (array_key_exists($openBraces, $this->currentClass) && is_null($this->currentClass[$openBraces]))
            {
              $this->currentClass[$openBraces] = $text;
            }
            if (array_key_exists($openBraces, $this->currentFunction) && is_null($this->currentFunction[$openBraces]))
            {
              $this->currentFunction[$openBraces] = $text;
            }
            break;
          case T_AND_EQUAL:
          case T_BREAK:
          case T_CASE:
          case T_CATCH:
          case T_CLONE:
          case T_CONCAT_EQUAL:
          case T_CONTINUE:
          case T_DEC:
          case T_DECLARE:
          case T_DEFAULT:
          case T_DIV_EQUAL:
          case T_DO:
          case T_ECHO:
          case T_ELSEIF:
          case T_EMPTY:
          case T_ENDDECLARE:
          case T_ENDFOR:
          case T_ENDFOREACH:
          case T_ENDIF:
          case T_ENDSWITCH:
          case T_ENDWHILE:
          case T_END_HEREDOC:
          case T_EVAL:
          case T_EXIT:
          case T_FOR:
          case T_FOREACH:
          case T_GLOBAL:
          case T_IF:
          case T_INC:
          case T_INCLUDE:
          case T_INCLUDE_ONCE:
          case T_INSTANCEOF:
          case T_ISSET:
          case T_IS_EQUAL:
          case T_IS_GREATER_OR_EQUAL:
          case T_IS_IDENTICAL:
          case T_IS_NOT_EQUAL:
          case T_IS_NOT_IDENTICAL:
          case T_IS_SMALLER_OR_EQUAL:
          case T_LIST:
          case T_LOGICAL_AND:
          case T_LOGICAL_OR:
          case T_LOGICAL_XOR:
          case T_MINUS_EQUAL:
          case T_MOD_EQUAL:
          case T_MUL_EQUAL:
          case T_NEW:
          case T_OBJECT_OPERATOR:
          case T_OR_EQUAL:
          case T_PLUS_EQUAL:
          case T_PRINT:
          case T_REQUIRE:
          case T_REQUIRE_ONCE:
          case T_RETURN:
          case T_SL:
          case T_SL_EQUAL:
          case T_SR:
          case T_SR_EQUAL:
          case T_SWITCH:
          case T_THROW:
          case T_TRY:
          case T_UNSET:
          case T_UNSET_CAST:
          case T_USE:
          case T_WHILE:
          case T_XOR_EQUAL:
            $this->endOfCurrentExpr = false;
            break;
        }

        if ($this->endOfCurrentExpr)
        {
          $this->inAssignment = false;
        }

        $this->beforeProcess($text, $id);
        $this->process($text, $id);
        $this->afterProcess($text, $id);
      }

      if (!$this->continue)
      {
        break;
      }
    }

    return $this->getResult();
  }

  protected function beforeProcess($text, $id)
  {
  }

  protected function afterProcess($text, $id)
  {
  }

  /**
   * Processes a token in the PHP code.
   *
   * @param  string  $text  The string representation of the token
   * @param  integer $id    The token identifier (f.i. T_VARIABLE) or NULL, if
   *                        the token does not have an identifier.
   */
  abstract protected function process($text, $id);

  /**
   * Returns the result of the lexing process.
   *
   * @return mixed
   */
  abstract protected function getResult();

  /**
   * Returns the line number at the current position of the lexer.
   *
   * @return integer
   */
  protected function getCurrentLine()
  {
    return $this->currentLine;
  }

  /**
   * Returns the class name at the current position of the lexer.
   *
   * @return string  Returns NULL if the current position is not inside a class.
   */
  protected function getCurrentClass()
  {
    return $this->inClass() ? end($this->currentClass) : null;
  }

  /**
   * Returns the function name at the current position of the lexer.
   *
   * @return string  Returns NULL if the current position is not inside a function.
   */
  protected function getCurrentFunction()
  {
    return $this->inFunction() ? end($this->currentFunction) : null;
  }

  /**
   * Returns whether the current position of the lexer is inside a class.
   *
   * @return boolean
   */
  protected function inClass()
  {
    return count($this->currentClass) > 0;
  }

  /**
   * Returns whether the current position of the lexer is inside a class
   * declaration (f.i. "abstract class ClassName extends BaseClass").
   *
   * @return boolean
   */
  protected function inClassDeclaration()
  {
    return $this->inClassDeclaration;
  }

  /**
   * Returns whether the current position of the lexer is inside a function.
   *
   * @return boolean
   */
  protected function inFunction()
  {
    return count($this->currentFunction) > 0;
  }

  /**
   * Returns whether the current position of the lexer is inside a function
   * declaration (f.i. "protected function myFunctionName()").
   *
   * @return boolean
   */
  protected function inFunctionDeclaration()
  {
    return $this->inFunctionDeclaration;
  }

  /**
   * Returns how many functions are currently nested inside each other.
   *
   * @return integer
   */
  protected function getFunctionNestingLevel()
  {
    return count($this->currentFunction);
  }

  /**
   * Returns whether the current token marks the end of the last expression.
   *
   * @return boolean
   */
  protected function isEndOfCurrentExpr()
  {
    return $this->endOfCurrentExpr;
  }

  /**
   * Returns whether the current token is inside an assignment operation.
   *
   * @return boolean
   */
  protected function inAssignment()
  {
    return $this->inAssignment;
  }

  /**
   * Tells the lexer to stop lexing.
   */
  protected function stop()
  {
    $this->continue = false;
  }
}