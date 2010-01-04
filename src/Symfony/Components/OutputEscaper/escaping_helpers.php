<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * The functions are primarily used by the output escaping component.
 *
 * Each function specifies a way for applying a transformation to a string
 * passed to it. The purpose is for the string to be "escaped" so it is
 * suitable for the format it is being displayed in.
 *
 * For example, the string: "It's required that you enter a username & password.\n"
 * If this were to be displayed as HTML it would be sensible to turn the
 * ampersand into '&amp;' and the apostrophe into '&aps;'. However if it were
 * going to be used as a string in JavaScript to be displayed in an alert box
 * it would be right to leave the string as-is, but c-escape the apostrophe and
 * the new line.
 *
 * For each function there is a define to avoid problems with strings being
 * incorrectly specified.
 *
 * @package    symfony
 * @subpackage helper
 * @author     Mike Squire <mike@somosis.co.uk>
 * @version    SVN: $Id: EscapingHelper.php 18907 2009-06-04 09:36:30Z FabianLange $
 */

/**
 * Runs the PHP function htmlentities on the value passed.
 *
 * @param string $value the value to escape
 * @return string the escaped value
 */
function esc_entities($value)
{
  // Numbers and boolean values get turned into strings which can cause problems
  // with type comparisons (e.g. === or is_int() etc).
  return is_string($value) ? htmlentities($value, ENT_QUOTES, Symfony\Components\OutputEscaper\Escaper::getCharset()) : $value;
}

define('ESC_ENTITIES', 'esc_entities');

/**
 * Runs the PHP function htmlspecialchars on the value passed.
 *
 * @param string $value the value to escape
 * @return string the escaped value
 */
function esc_specialchars($value)
{
  // Numbers and boolean values get turned into strings which can cause problems
  // with type comparisons (e.g. === or is_int() etc).
  return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, Symfony\Components\OutputEscaper\Escaper::getCharset()) : $value;
}

define('ESC_SPECIALCHARS', 'esc_specialchars');

/**
 * An identity function that merely returns that which it is given, the purpose
 * being to be able to specify that the value is not to be escaped in any way.
 *
 * @param string $value the value to escape
 * @return string the escaped value
 */
function esc_raw($value)
{
  return $value;
}

define('ESC_RAW', 'esc_raw');

/**
 * A function that c-escapes a string after applying {@link esc_entities()}. The
 * assumption is that the value will be used to generate dynamic HTML in some
 * way and the safest way to prevent mishap is to assume the value should have
 * HTML entities set properly.
 *
 * The {@link esc_js_no_entities()} method should be used to escape a string
 * that is ultimately not going to end up as text in an HTML document.
 *
 * @param string $value the value to escape
 * @return string the escaped value
 */
function esc_js($value)
{
  return esc_js_no_entities(esc_entities($value));
}

define('ESC_JS', 'esc_js');

/**
 * A function the c-escapes a string, making it suitable to be placed in a
 * JavaScript string.
 *
 * @param string $value the value to escape
 * @return string the escaped value
 */
function esc_js_no_entities($value)
{
  return str_replace(array("\\"  , "\n"  , "\r" , "\""  , "'"  ),
                     array("\\\\", "\\n" , "\\r", "\\\"", "\\'"),
                     $value);
}

define('ESC_JS_NO_ENTITIES', 'esc_js_no_entities');
