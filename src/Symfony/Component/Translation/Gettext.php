<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

/**
 * Provide for specific Gettext related helper functionality.
 *
 * @see http://www.gnu.org/software/gettext/manual/gettext.html#PO-Files
 * @author Clemens Tolboom
 * @copyright Clemens Tolboom clemens@build2be.com
 */
class Gettext
{
    /**
     * Defines a key for managing a PO Header in our messages.
     *
     * Gettext files (.po .mo) can contain a header which needs to be managed.
     * A Gettext file can have multiple domains into one file. This is called
     * a message context or msgctxt.
     *
     * To provide for both header and context this class provides for
     * some static functions to (help) process their values.
     *
     * @see GettextTest
     * @see PoFileLoader
     * @see PoFileLoaderTest
     * @see PoFileDumper
     * @see PoFileDumperTest
     */
    const HEADER_KEY = "__HEADER__";
    const CONTEXT_KEY = "__CONTEXT__";

    /**
     * Merge key/value pair into Gettext compatible item.
     *
     * Each combination is into substring: "key: value \n".
     *
     * If any key found the values are preceded by empty msgid and msgstr
     *
     * @param array $header
     * @return array|NULL A Gettext compatible string.
     */
    static public function headerToString(array $header)
    {
        $zipped = Gettext::zipHeader($header);
        if (!empty($zipped)) {
           $result = array(
               'msgid ""',
               'msgstr ""',
               $zipped,
           );

           return implode("\n", $result);
        }
    }

    /**
     * Ordered list of Gettext header keys
     *
     * TODO: this list is probably incomplete
     *
     * @return array Ordered list of Gettext keys
     */
    static public function headerKeys() {
        return array(
            'Project-Id-Version',
            'POT-Creation-Date',
            'PO-Revision-Date',
            'Last-Translator',
            'Language-Team',
            'MIME-Version',
            'Content-Type',
            'Content-Transfer-Encoding',
            'Plural-Forms'
        );
    }

    static public function emptyHeader() {
        return array_fill_keys(Gettext::headerKeys(), "");
    }

    /**
     * Retrieve PO Header from messages.
     *
     * @param array $messages
     * @return array containing key/value pair|empty array.
     */
    static public function getHeader(array &$messages)
    {
        if (isset($messages[Gettext::HEADER_KEY])) {
           return Gettext::unzipHeader($messages[Gettext::HEADER_KEY]);
        }

        return array();
    }

    /**
     * Adds or overwrite a header to the messages.
     *
     * @param array $messages
     * @param array $header
     */
    static public function addHeader(array &$messages, array $header)
    {
        $messages[Gettext::HEADER_KEY] = Gettext::zipHeader($header);
    }

    /**
     * Deletes a header from the messages if exists.
     *
     * @param array $messages
     */
    static public function deleteHeader(array &$messages) {
        if (isset($messages[Gettext::HEADER_KEY])) {
            unset($messages[Gettext::HEADER_KEY]);
        }
    }

    /**
     * Add context to the messages.
     *
     * Gettext supports for multiple context (domains) in one PO|MO file.
     * By injecting these into the translated messages we can post process.
     *
     * @param array $messages
     * @param type  $context
     */
    static public function addContext(array &$messages, $context) {
        if (!isset($messages[Gettext::CONTEXT_KEY])) {
            $messages[Gettext::CONTEXT_KEY] = '';
        }
        $contexts = array_flip(explode('|', $messages[Gettext::CONTEXT_KEY]));
        $contexts[$context] = $context;
        unset($contexts['']);
        $messages[Gettext::CONTEXT_KEY] = implode('|', array_keys($contexts));
    }

    static public function deleteContext(array &$messages) {
        unset($messages[Gettext::CONTEXT_KEY]);
    }

    static public function getContext(array &$messages) {
        if (isset($messages[Gettext::CONTEXT_KEY])) {
            return explode('|', $messages[Gettext::CONTEXT_KEY]);
        }

        return array();
    }

    /**
     * Parses a Gettext header string into a key/value pairs.
     *
     * @param $header
     *   The Gettext header.
     * @return array
     *   Array with the key/value pair
     */
    static private function unzipHeader($header)
    {
        $result = array();
        $lines = explode("\n", $header);
        foreach ($lines as $line) {
            $cleaned = trim($line);
            $cleaned = preg_replace(array('/^\"/','/\\\n\"$/'), '', $cleaned);
            if (strpos($cleaned, ':') > 0) {
                list($key, $value) = explode(':', $cleaned, 2);
                $result[trim($key)] = trim($value);
            }
        }

        return $result;
    }

    /**
     * Zips header into a Gettext formatted string.
     *
     * The returned value is what msgstr would contain when used by the header
     * in a Gettext file.
     *
     * @param array $header
     * @return string
     *
     * @see unzipHeader().
     * @see fixtures/full.po
     */
    static private function zipHeader(array $header)
    {
        $lines = array();
        foreach ($header as $key => $value) {
            $lines[] = '"' . $key . ": " . $value . '\n"';
        }

        return implode("\n", $lines);
    }

}
