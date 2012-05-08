<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Config\Resource\FileResource;

/**
 * @copyright Copyright (c) 2010, Union of RAD http://union-of-rad.org (http://lithify.me/)
 */
class PoFileLoader extends ArrayLoader implements LoaderInterface
{
    public function load($resource, $locale, $domain = 'messages')
    {
        $messages = $this->parse($resource);

        // empty file
        if (null === $messages) {
            $messages = array();
        }

        // not an array
        if (!is_array($messages)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" must contain a valid po file.', $resource));
        }

        $catalogue = parent::load($messages, $locale, $domain);
        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }

    /**
     * Parses portable object (PO) format.
     *
     * This parser sacrifices some features of the reference implementation the
     * differences to that implementation are as follows.
     * - No support for comments spanning multiple lines.
     * - Translator and extracted comments are treated as being the same type.
     * - Message IDs are allowed to have other encodings as just US-ASCII.
     *
     * Items with an empty id are ignored.
     *
     * @param resource $resource
     *
     * @return array
     */
    private function parse($resource)
    {
        $stream = fopen($resource, 'r');

        $defaults = array(
            'ids' => array(),
            'translated' => null,
        );

        $messages = array();
        $item = $defaults;

        // Prepare state
        $this->lineno = 0;
        $this->_fd = $stream;
        $this->finished = FALSE;
        $this->context = 'COMMENT';

        while(!$this->finished) {
          $this->readItem();
          $translation = $this->translation;
          $this->translation = null;
          // now map our parsed data to $messages structure
          if (!is_null($translation)) {
            //var_dump($translation);
            if ($translation->plural) {
              $messages[$translation->source[0]] = $translation->translation[0];
              //var_dump($translation);
              $trans = $translation->translation;
              $plurals = array();
              foreach ($trans as $plural => $translated) {
                $plurals[] = sprintf('{%d} %s', $plural, $translated);
              }
              $messages[$translation->source[1]] = stripcslashes(implode('|', $plurals));
            }
            else {
              $messages[$translation->source] = $translation->translation;
            }
          }
        }

        fclose($stream);

        return array_filter($messages);
    }

    /*
     * Code is taken from http://drupal.org/node/1189184 work in progres
     *
     * That is it is copied verbatim from the mentioned patch applied to
     * Drupal Core then pasted into here.
     *
     * Edits aka exceptions are:
     * - "new POItem()" is replaced by "(object) array()"
     *
     * TODO:
     * - add a header to po file to test it is skipped nicely
     */

  /**
   * Reads the header from the given input stream.
   *
   * We need to read the optional first COMMENT
   * Next read a MSGID and a MSGSTR
   *
   * TODO: is a header required?
   */
  private function readHeader() {
    $translation = $this->readTranslation();
    $header = new PoHeader;
    $header->setFromString(trim($translation->translation));
    $this->_header = $header;
  }

  /**
   * Return a translation object (singular or plural)
   *
   * @todo Define a translation object for this purpose?
   *       Or use a standard class for better performance?
   */
  public function readItem() {
    $this->readTranslation();
    return $this->translation;
  }

  private function readTranslation() {
    $this->translation = NULL;
    while (!$this->finished && is_null($this->translation)) {
      $this->readLine();
    }
    return $this->translation;
  }

  /**
   * Reads a line from a PO file.
   *
   * While reading a line it's content is processed according to current
   * context.
   *
   * The parser context. Can be:
   *  - 'COMMENT' (#)
   *  - 'MSGID' (msgid)
   *  - 'MSGID_PLURAL' (msgid_plural)
   *  - 'MSGCTXT' (msgctxt)
   *  - 'MSGSTR' (msgstr or msgstr[])
   *  - 'MSGSTR_ARR' (msgstr_arg)
   *
   * @return boolean FALSE or NULL
   */
  private function readLine() {
    // a string or boolean FALSE
    $line = fgets($this->_fd);
    $this->finished = ($line === FALSE);
    if (!$this->finished) {

      if ($this->lineno == 0) {
        // The first line might come with a UTF-8 BOM, which should be removed.
        $line = str_replace("\xEF\xBB\xBF", '', $line);
        // Current plurality for 'msgstr[]'.
        $this->plural = 0;
      }

      $this->lineno++;

      // Trim away the linefeed.
      $line = trim(strtr($line, array("\\\n" => "")));

      if (!strncmp('#', $line, 1)) {
        // Lines starting with '#' are comments.

        if ($this->context == 'COMMENT') {
          // Already in comment token, insert the comment.
          $this->current['#'][] = substr($line, 1);
        }
        elseif (($this->context == 'MSGSTR') || ($this->context == 'MSGSTR_ARR')) {
          // We are currently in string token, close it out.
          $this->saveOneString();

          // Start a new entry for the comment.
          $this->current = array();
          $this->current['#'][] = substr($line, 1);

          $this->context = 'COMMENT';
          return TRUE;
        }
        else {
          // A comment following any other token is a syntax error.
          $this->log('The translation file %filename contains an error: "msgstr" was expected but not found on line %line.', $this->lineno);
          return FALSE;
        }
        return;
      }
      elseif (!strncmp('msgid_plural', $line, 12)) {
        // A plural form for the current message.

        if ($this->context != 'MSGID') {
          // A plural form cannot be added to anything else but the id directly.
          $this->log('The translation file %filename contains an error: "msgid_plural" was expected but not found on line %line.', $this->lineno);
          return FALSE;
        }

        // Remove 'msgid_plural' and trim away whitespace.
        $line = trim(substr($line, 12));
        // At this point, $line should now contain only the plural form.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The plural form must be wrapped in quotes.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        // Append the plural form to the current entry.
        if (is_string($this->current['msgid'])) {
          // The first value was stored as string. Now we know the context is
          // plural, it is converted to array.
          $this->current['msgid'] = array($this->current['msgid']);
        }
        $this->current['msgid'][] = $quoted;

        $this->context = 'MSGID_PLURAL';
        return;
      }
      elseif (!strncmp('msgid', $line, 5)) {
        // Starting a new message.

        if (($this->context == 'MSGSTR') || ($this->context == 'MSGSTR_ARR')) {
          // We are currently in a message string, close it out.
          $this->saveOneString();

          // Start a new context for the id.
          $this->current = array();
        }
        elseif ($this->context == 'MSGID') {
          // We are currently already in the context, meaning we passed an id with no data.
          $this->log('The translation file %filename contains an error: "msgid" is unexpected on line %line.', $this->lineno);
          return FALSE;
        }

        // Remove 'msgid' and trim away whitespace.
        $line = trim(substr($line, 5));
        // At this point, $line should now contain only the message id.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The message id must be wrapped in quotes.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        $this->current['msgid'] = $quoted;
        $this->context = 'MSGID';
        return;
      }
      elseif (!strncmp('msgctxt', $line, 7)) {
        // Starting a new context.

        if (($this->context == 'MSGSTR') || ($this->context == 'MSGSTR_ARR')) {
          // We are currently in a message, start a new one.
          $this->saveOneString($this->current);
          $this->current = array();
        }
        elseif (!empty($this->current['msgctxt'])) {
          // A context cannot apply to another context.
          $this->log('The translation file %filename contains an error: "msgctxt" is unexpected on line %line.', $this->lineno);
          return FALSE;
        }

        // Remove 'msgctxt' and trim away whitespaces.
        $line = trim(substr($line, 7));
        // At this point, $line should now contain the context.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The context string must be quoted.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        $this->current['msgctxt'] = $quoted;

        $this->context = 'MSGCTXT';
        return;
      }
      elseif (!strncmp('msgstr[', $line, 7)) {
        // A message string for a specific plurality.

        if (($this->context != 'MSGID') && ($this->context != 'MSGCTXT') && ($this->context != 'MSGID_PLURAL') && ($this->context != 'MSGSTR_ARR')) {
          // Message strings must come after msgid, msgxtxt, msgid_plural, or other msgstr[] entries.
          $this->log('The translation file %filename contains an error: "msgstr[]" is unexpected on line %line.', $this->lineno);
          return FALSE;
        }

        // Ensure the plurality is terminated.
        if (strpos($line, ']') === FALSE) {
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        // Extract the plurality.
        $frombracket = strstr($line, '[');
        $this->plural = substr($frombracket, 1, strpos($frombracket, ']') - 1);

        // Skip to the next whitespace and trim away any further whitespace, bringing $line to the message data.
        $line = trim(strstr($line, " "));

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The string must be quoted.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }
        if (!isset($this->current['msgstr']) || !is_array($this->current['msgstr'])) {
          $this->current['msgstr'] = array();
        }

        $this->current['msgstr'][$this->plural] = $quoted;

        $this->context = 'MSGSTR_ARR';
        return;
      }
      elseif (!strncmp("msgstr", $line, 6)) {
        // A string for the an id or context.

        if (($this->context != 'MSGID') && ($this->context != 'MSGCTXT')) {
          // Strings are only valid within an id or context scope.
          $this->log('The translation file %filename contains an error: "msgstr" is unexpected on line %line.', $this->lineno);
          return FALSE;
        }

        // Remove 'msgstr' and trim away away whitespaces.
        $line = trim(substr($line, 6));
        // At this point, $line should now contain the message.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The string must be quoted.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        $this->current['msgstr'] = $quoted;

        $this->context = 'MSGSTR';
        return;
      }
      elseif ($line != '') {
        // Anything that is not a token may be a continuation of a previous token.

        $quoted = $this->parseQuoted($line);
        if ($quoted === FALSE) {
          // The string must be quoted.
          $this->log('The translation file %filename contains a syntax error on line %line.', $this->lineno);
          return FALSE;
        }

        // Append the string to the current context.
        if (($this->context == 'MSGID') || ($this->context == 'MSGID_PLURAL')) {
          if (is_array($this->current['msgid'])) {
            // Add string to last array element.
            $last_index = count($this->current['msgid']) - 1;
            $this->current['msgid'][$last_index] .= $quoted;
          }
          else {
            $this->current['msgid'] .= $quoted;
          }
        }
        elseif ($this->context == 'MSGCTXT') {
          $this->current['msgctxt'] .= $quoted;
        }
        elseif ($this->context == 'MSGSTR') {
          $this->current['msgstr'] .= $quoted;
        }
        elseif ($this->context == 'MSGSTR_ARR') {
          $this->current['msgstr'][$this->plural] .= $quoted;
        }
        else {
          // No valid context to append to.
          $this->log('The translation file %filename contains an error: there is an unexpected string on line %line.', $this->lineno);
          return FALSE;
        }
        return;
      }
    }

    // Empty line read or EOF of PO file, closed out the last entry.
    if (($this->context == 'MSGSTR') || ($this->context == 'MSGSTR_ARR')) {
      $this->saveOneString($this->current);
      $this->current = array();
    }
    elseif ($this->context != 'COMMENT') {
      $this->log('The translation file %filename ended unexpectedly at line %line.', $this->lineno);
      return FALSE;
    }
  }

  /**
   * Sets an error message if an error occurred during locale file parsing.
   *
   * @param $message
   *   The message to be translated.
   * @param $lineno
   *   An optional line number argument.
   */
  protected function log($message, $lineno = NULL) {
    if (isset($lineno)) {
      $vars['%line'] = $lineno;
    }
    $t = get_t();
    $this->errorLog[] = $t($message, $vars);
  }

  /**
   * Store the parsed values as translation object.
   */
  public function saveOneString() {
    $value = $this->current;
    $plural = FALSE;

    $comments = '';
    if (isset($value['#'])) {
      $comments = $this->shortenComments($value['#']);
    }

    if (is_array($value['msgstr'])) {
      // Sort plural variants by their form index.
      ksort($value['msgstr']);
      $plural = TRUE;
    }

    $translation = (object) array();
    $translation->context = isset($value['msgctxt']) ? $value['msgctxt'] : '';
    $translation->source = $value['msgid'];
    $translation->translation = $value['msgstr'];
    $translation->plural = $plural;
    $translation->comment = $comments;

    $this->translation = $translation;

    $this->context = 'COMMENT';
  }

  /**
   * Parses a string in quotes.
   *
   * @param $string
   *   A string specified with enclosing quotes.
   *
   * @return
   *   The string parsed from inside the quotes.
   */
  function parseQuoted($string) {
    if (substr($string, 0, 1) != substr($string, -1, 1)) {
      return FALSE;   // Start and end quotes must be the same
    }
    $quote = substr($string, 0, 1);
    $string = substr($string, 1, -1);
    if ($quote == '"') {        // Double quotes: strip slashes
      return stripcslashes($string);
    }
    elseif ($quote == "'") {  // Simple quote: return as-is
      return $string;
    }
    else {
      return FALSE;             // Unrecognized quote
    }
  }

}
