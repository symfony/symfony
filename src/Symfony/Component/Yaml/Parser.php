<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml;

use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Parser parses YAML strings to convert them to PHP arrays.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Parser
{
    private $offset         = 0;
    private $lines          = array();
    private $currentLineNb  = -1;
    private $currentLine    = '';
    private $refs           = array();

    /**
     * Constructor
     *
     * @param integer $offset The offset of YAML document (used for line numbers in error messages)
     */
    public function __construct($offset = 0)
    {
        $this->offset = $offset;
    }

    /**
     * Parses a YAML string to a PHP value.
     *
     * @param string  $value                  A YAML string
     * @param Boolean $exceptionOnInvalidType true if an exception must be thrown on invalid types (a PHP resource or object), false otherwise
     * @param Boolean $objectSupport          true if object support is enabled, false otherwise
     *
     * @return mixed  A PHP value
     *
     * @throws ParseException If the YAML is not valid
     */
    public function parse($value, $exceptionOnInvalidType = false, $objectSupport = false)
    {
        $this->currentLineNb = -1;
        $this->currentLine = '';
        $this->lines = explode("\n", $this->cleanup($value));

        if (function_exists('mb_detect_encoding') && false === mb_detect_encoding($value, 'UTF-8', true)) {
            throw new ParseException('The YAML value does not appear to be valid UTF-8.');
        }

        if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
            $mbEncoding = mb_internal_encoding();
            mb_internal_encoding('UTF-8');
        }

        $data = array();
        while ($this->moveToNextLine()) {
            if ($this->isCurrentLineEmpty()) {
                continue;
            }

            // tab?
            if ("\t" === $this->currentLine[0]) {
                throw new ParseException('A YAML file cannot contain tabs as indentation.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
            }

            $isRef = $isInPlace = $isProcessed = false;
            if (preg_match('#^\-((?P<leadspaces>\s+)(?P<value>.+?))?\s*$#u', $this->currentLine, $values)) {
                if (isset($values['value']) && preg_match('#^&(?P<ref>[^ ]+) *(?P<value>.*)#u', $values['value'], $matches)) {
                    $isRef = $matches['ref'];
                    $values['value'] = $matches['value'];
                }

                // array
                if (!isset($values['value']) || '' == trim($values['value'], ' ') || 0 === strpos(ltrim($values['value'], ' '), '#')) {
                    $c = $this->getRealCurrentLineNb() + 1;
                    $parser = new Parser($c);
                    $parser->refs =& $this->refs;
                    $data[] = $parser->parse($this->getNextEmbedBlock(), $exceptionOnInvalidType, $objectSupport);
                } else {
                    if (isset($values['leadspaces'])
                        && ' ' == $values['leadspaces']
                        && preg_match('#^(?P<key>'.Inline::REGEX_QUOTED_STRING.'|[^ \'"\{\[].*?) *\:(\s+(?P<value>.+?))?\s*$#u', $values['value'], $matches)
                    ) {
                        // this is a compact notation element, add to next block and parse
                        $c = $this->getRealCurrentLineNb();
                        $parser = new Parser($c);
                        $parser->refs =& $this->refs;

                        $block = $values['value'];
                        if (!$this->isNextLineIndented()) {
                            $block .= "\n".$this->getNextEmbedBlock($this->getCurrentLineIndentation() + 2);
                        }

                        $data[] = $parser->parse($block, $exceptionOnInvalidType, $objectSupport);
                    } else {
                        $data[] = $this->parseValue($values['value'], $exceptionOnInvalidType, $objectSupport);
                    }
                }
            } elseif (preg_match('#^(?P<key>'.Inline::REGEX_QUOTED_STRING.'|[^ \'"\[\{].*?) *\:(\s+(?P<value>.+?))?\s*$#u', $this->currentLine, $values)) {
                // force correct settings
                Inline::parse(null, $exceptionOnInvalidType, $objectSupport);
                try {
                    $key = Inline::parseScalar($values['key']);
                } catch (ParseException $e) {
                    $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                    $e->setSnippet($this->currentLine);

                    throw $e;
                }

                if ('<<' === $key) {
                    if (isset($values['value']) && 0 === strpos($values['value'], '*')) {
                        $isInPlace = substr($values['value'], 1);
                        if (!array_key_exists($isInPlace, $this->refs)) {
                            throw new ParseException(sprintf('Reference "%s" does not exist.', $isInPlace), $this->getRealCurrentLineNb() + 1, $this->currentLine);
                        }
                    } else {
                        if (isset($values['value']) && $values['value'] !== '') {
                            $value = $values['value'];
                        } else {
                            $value = $this->getNextEmbedBlock();
                        }
                        $c = $this->getRealCurrentLineNb() + 1;
                        $parser = new Parser($c);
                        $parser->refs =& $this->refs;
                        $parsed = $parser->parse($value, $exceptionOnInvalidType, $objectSupport);

                        $merged = array();
                        if (!is_array($parsed)) {
                            throw new ParseException('YAML merge keys used with a scalar value instead of an array.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                        } elseif (isset($parsed[0])) {
                            // Numeric array, merge individual elements
                            foreach (array_reverse($parsed) as $parsedItem) {
                                if (!is_array($parsedItem)) {
                                    throw new ParseException('Merge items must be arrays.', $this->getRealCurrentLineNb() + 1, $parsedItem);
                                }
                                $merged = array_merge($parsedItem, $merged);
                            }
                        } else {
                            // Associative array, merge
                            $merged = array_merge($merged, $parsed);
                        }

                        $isProcessed = $merged;
                    }
                } elseif (isset($values['value']) && preg_match('#^&(?P<ref>[^ ]+) *(?P<value>.*)#u', $values['value'], $matches)) {
                    $isRef = $matches['ref'];
                    $values['value'] = $matches['value'];
                }

                if ($isProcessed) {
                    // Merge keys
                    $data = $isProcessed;
                // hash
                } elseif (!isset($values['value']) || '' == trim($values['value'], ' ') || 0 === strpos(ltrim($values['value'], ' '), '#')) {
                    // if next line is less indented or equal, then it means that the current value is null
                    if ($this->isNextLineIndented()) {
                        $data[$key] = null;
                    } else {
                        $c = $this->getRealCurrentLineNb() + 1;
                        $parser = new Parser($c);
                        $parser->refs =& $this->refs;
                        $data[$key] = $parser->parse($this->getNextEmbedBlock(), $exceptionOnInvalidType, $objectSupport);
                    }
                } else {
                    if ($isInPlace) {
                        $data = $this->refs[$isInPlace];
                    } else {
                        $data[$key] = $this->parseValue($values['value'], $exceptionOnInvalidType, $objectSupport);
                    }
                }
            } else {
                // 1-liner optionally followed by newline
                $lineCount = count($this->lines);
                if (1 === $lineCount || (2 === $lineCount && empty($this->lines[1]))) {
                    try {
                        $value = Inline::parse($this->lines[0], $exceptionOnInvalidType, $objectSupport);
                    } catch (ParseException $e) {
                        $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                        $e->setSnippet($this->currentLine);

                        throw $e;
                    }

                    if (is_array($value)) {
                        $first = reset($value);
                        if (is_string($first) && 0 === strpos($first, '*')) {
                            $data = array();
                            foreach ($value as $alias) {
                                $data[] = $this->refs[substr($alias, 1)];
                            }
                            $value = $data;
                        }
                    }

                    if (isset($mbEncoding)) {
                        mb_internal_encoding($mbEncoding);
                    }

                    return $value;
                }

                switch (preg_last_error()) {
                    case PREG_INTERNAL_ERROR:
                        $error = 'Internal PCRE error.';
                        break;
                    case PREG_BACKTRACK_LIMIT_ERROR:
                        $error = 'pcre.backtrack_limit reached.';
                        break;
                    case PREG_RECURSION_LIMIT_ERROR:
                        $error = 'pcre.recursion_limit reached.';
                        break;
                    case PREG_BAD_UTF8_ERROR:
                        $error = 'Malformed UTF-8 data.';
                        break;
                    case PREG_BAD_UTF8_OFFSET_ERROR:
                        $error = 'Offset doesn\'t correspond to the begin of a valid UTF-8 code point.';
                        break;
                    default:
                        $error = 'Unable to parse.';
                }

                throw new ParseException($error, $this->getRealCurrentLineNb() + 1, $this->currentLine);
            }

            if ($isRef) {
                $this->refs[$isRef] = end($data);
            }
        }

        if (isset($mbEncoding)) {
            mb_internal_encoding($mbEncoding);
        }

        return empty($data) ? null : $data;
    }

    /**
     * Returns the current line number (takes the offset into account).
     *
     * @return integer The current line number
     */
    private function getRealCurrentLineNb()
    {
        return $this->currentLineNb + $this->offset;
    }

    /**
     * Returns the current line indentation.
     *
     * @return integer The current line indentation
     */
    private function getCurrentLineIndentation()
    {
        return strlen($this->currentLine) - strlen(ltrim($this->currentLine, ' '));
    }

    /**
     * Returns the next embed block of YAML.
     *
     * @param integer $indentation The indent level at which the block is to be read, or null for default
     *
     * @return string A YAML string
     *
     * @throws ParseException When indentation problem are detected
     */
    private function getNextEmbedBlock($indentation = null)
    {
        $this->moveToNextLine();

        if (null === $indentation) {
            $newIndent = $this->getCurrentLineIndentation();

            if (!$this->isCurrentLineEmpty() && 0 == $newIndent) {
                throw new ParseException('Indentation problem.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
            }
        } else {
            $newIndent = $indentation;
        }

        $data = array(substr($this->currentLine, $newIndent));

        while ($this->moveToNextLine()) {
            if ($this->isCurrentLineEmpty()) {
                if ($this->isCurrentLineBlank()) {
                    $data[] = substr($this->currentLine, $newIndent);
                }

                continue;
            }

            $indent = $this->getCurrentLineIndentation();

            if (preg_match('#^(?P<text> *)$#', $this->currentLine, $match)) {
                // empty line
                $data[] = $match['text'];
            } elseif ($indent >= $newIndent) {
                $data[] = substr($this->currentLine, $newIndent);
            } elseif (0 == $indent) {
                $this->moveToPreviousLine();

                break;
            } else {
                throw new ParseException('Indentation problem.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
            }
        }

        return implode("\n", $data);
    }

    /**
     * Moves the parser to the next line.
     *
     * @return Boolean
     */
    private function moveToNextLine()
    {
        if ($this->currentLineNb >= count($this->lines) - 1) {
            return false;
        }

        $this->currentLine = $this->lines[++$this->currentLineNb];

        return true;
    }

    /**
     * Moves the parser to the previous line.
     */
    private function moveToPreviousLine()
    {
        $this->currentLine = $this->lines[--$this->currentLineNb];
    }

    /**
     * Parses a YAML value.
     *
     * @param string $value A YAML value
     *
     * @return mixed  A PHP value
     *
     * @throws ParseException When reference does not exist
     */
    private function parseValue($value, $exceptionOnInvalidType, $objectSupport)
    {
        if (0 === strpos($value, '*')) {
            if (false !== $pos = strpos($value, '#')) {
                $value = substr($value, 1, $pos - 2);
            } else {
                $value = substr($value, 1);
            }

            if (!array_key_exists($value, $this->refs)) {
                throw new ParseException(sprintf('Reference "%s" does not exist.', $value), $this->currentLine);
            }

            return $this->refs[$value];
        }

        if (preg_match('/^(?P<separator>\||>)(?P<modifiers>\+|\-|\d+|\+\d+|\-\d+|\d+\+|\d+\-)?(?P<comments> +#.*)?$/', $value, $matches)) {
            $modifiers = isset($matches['modifiers']) ? $matches['modifiers'] : '';

            return $this->parseFoldedScalar($matches['separator'], preg_replace('#\d+#', '', $modifiers), intval(abs($modifiers)));
        }

        try {
            return Inline::parse($value, $exceptionOnInvalidType, $objectSupport);
        } catch (ParseException $e) {
            $e->setParsedLine($this->getRealCurrentLineNb() + 1);
            $e->setSnippet($this->currentLine);

            throw $e;
        }
    }

    /**
     * Parses a folded scalar.
     *
     * @param string  $separator   The separator that was used to begin this folded scalar (| or >)
     * @param string  $indicator   The indicator that was used to begin this folded scalar (+ or -)
     * @param integer $indentation The indentation that was used to begin this folded scalar
     *
     * @return string  The text value
     */
    private function parseFoldedScalar($separator, $indicator = '', $indentation = 0)
    {
        $separator = '|' == $separator ? "\n" : ' ';
        $text = '';

        $notEOF = $this->moveToNextLine();

        while ($notEOF && $this->isCurrentLineBlank()) {
            $text .= "\n";

            $notEOF = $this->moveToNextLine();
        }

        if (!$notEOF) {
            return '';
        }

        if (!preg_match('#^(?P<indent>'.($indentation ? str_repeat(' ', $indentation) : ' +').')(?P<text>.*)$#u', $this->currentLine, $matches)) {
            $this->moveToPreviousLine();

            return '';
        }

        $textIndent = $matches['indent'];
        $previousIndent = 0;

        $text .= $matches['text'].$separator;
        while ($this->currentLineNb + 1 < count($this->lines)) {
            $this->moveToNextLine();

            if (preg_match('#^(?P<indent> {'.strlen($textIndent).',})(?P<text>.+)$#u', $this->currentLine, $matches)) {
                if (' ' == $separator && $previousIndent != $matches['indent']) {
                    $text = substr($text, 0, -1)."\n";
                }
                $previousIndent = $matches['indent'];

                $text .= str_repeat(' ', $diff = strlen($matches['indent']) - strlen($textIndent)).$matches['text'].($diff ? "\n" : $separator);
            } elseif (preg_match('#^(?P<text> *)$#', $this->currentLine, $matches)) {
                $text .= preg_replace('#^ {1,'.strlen($textIndent).'}#', '', $matches['text'])."\n";
            } else {
                $this->moveToPreviousLine();

                break;
            }
        }

        if (' ' == $separator) {
            // replace last separator by a newline
            $text = preg_replace('/ (\n*)$/', "\n$1", $text);
        }

        switch ($indicator) {
            case '':
                $text = preg_replace('#\n+$#s', "\n", $text);
                break;
            case '+':
                break;
            case '-':
                $text = preg_replace('#\n+$#s', '', $text);
                break;
        }

        return $text;
    }

    /**
     * Returns true if the next line is indented.
     *
     * @return Boolean Returns true if the next line is indented, false otherwise
     */
    private function isNextLineIndented()
    {
        $currentIndentation = $this->getCurrentLineIndentation();
        $notEOF = $this->moveToNextLine();

        while ($notEOF && $this->isCurrentLineEmpty()) {
            $notEOF = $this->moveToNextLine();
        }

        if (false === $notEOF) {
            return false;
        }

        $ret = false;
        if ($this->getCurrentLineIndentation() <= $currentIndentation) {
            $ret = true;
        }

        $this->moveToPreviousLine();

        return $ret;
    }

    /**
     * Returns true if the current line is blank or if it is a comment line.
     *
     * @return Boolean Returns true if the current line is empty or if it is a comment line, false otherwise
     */
    private function isCurrentLineEmpty()
    {
        return $this->isCurrentLineBlank() || $this->isCurrentLineComment();
    }

    /**
     * Returns true if the current line is blank.
     *
     * @return Boolean Returns true if the current line is blank, false otherwise
     */
    private function isCurrentLineBlank()
    {
        return '' == trim($this->currentLine, ' ');
    }

    /**
     * Returns true if the current line is a comment line.
     *
     * @return Boolean Returns true if the current line is a comment line, false otherwise
     */
    private function isCurrentLineComment()
    {
        //checking explicitly the first char of the trim is faster than loops or strpos
        $ltrimmedLine = ltrim($this->currentLine, ' ');

        return $ltrimmedLine[0] === '#';
    }

    /**
     * Cleanups a YAML string to be parsed.
     *
     * @param string $value The input YAML string
     *
     * @return string A cleaned up YAML string
     */
    private function cleanup($value)
    {
        $value = str_replace(array("\r\n", "\r"), "\n", $value);

        // strip YAML header
        $count = 0;
        $value = preg_replace('#^\%YAML[: ][\d\.]+.*\n#su', '', $value, -1, $count);
        $this->offset += $count;

        // remove leading comments
        $trimmedValue = preg_replace('#^(\#.*?\n)+#s', '', $value, -1, $count);
        if ($count == 1) {
            // items have been removed, update the offset
            $this->offset += substr_count($value, "\n") - substr_count($trimmedValue, "\n");
            $value = $trimmedValue;
        }

        // remove start of the document marker (---)
        $trimmedValue = preg_replace('#^\-\-\-.*?\n#s', '', $value, -1, $count);
        if ($count == 1) {
            // items have been removed, update the offset
            $this->offset += substr_count($value, "\n") - substr_count($trimmedValue, "\n");
            $value = $trimmedValue;

            // remove end of the document marker (...)
            $value = preg_replace('#\.\.\.\s*$#s', '', $value);
        }

        return $value;
    }
}
