<?php

/*
 * This file is part of the Symfony package.
 *
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
    const FOLDED_SCALAR_PATTERN = '(?P<separator>\||>)(?P<modifiers>\+|\-|\d+|\+\d+|\-\d+|\d+\+|\d+\-)?(?P<comments> +#.*)?';

    private $offset = 0;
    private $lines = array();
    private $currentLineNb = -1;
    private $currentLine = '';
    private $refs = array();

    /**
     * Constructor.
     *
     * @param int $offset The offset of YAML document (used for line numbers in error messages)
     */
    public function __construct($offset = 0)
    {
        $this->offset = $offset;
    }

    /**
     * Parses a YAML string to a PHP value.
     *
     * @param string $value                  A YAML string
     * @param bool   $exceptionOnInvalidType true if an exception must be thrown on invalid types (a PHP resource or object), false otherwise
     * @param bool   $objectSupport          true if object support is enabled, false otherwise
     * @param bool   $objectForMap           true if maps should return a stdClass instead of array()
     *
     * @return mixed A PHP value
     *
     * @throws ParseException If the YAML is not valid
     */
    public function parse($value, $exceptionOnInvalidType = false, $objectSupport = false, $objectForMap = false)
    {
        if (!preg_match('//u', $value)) {
            throw new ParseException('The YAML value does not appear to be valid UTF-8.');
        }
        $this->currentLineNb = -1;
        $this->currentLine = '';
        $value = $this->cleanup($value);
        $this->lines = explode("\n", $value);

        if (function_exists('mb_internal_encoding') && ((int) ini_get('mbstring.func_overload')) & 2) {
            $mbEncoding = mb_internal_encoding();
            mb_internal_encoding('UTF-8');
        }

        $data = array();
        $context = null;
        $allowOverwrite = false;
        while ($this->moveToNextLine()) {
            if ($this->isCurrentLineEmpty()) {
                continue;
            }

            // tab?
            if ("\t" === $this->currentLine[0]) {
                throw new ParseException('A YAML file cannot contain tabs as indentation.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
            }

            $isRef = $mergeNode = false;
            if (preg_match('#^\-((?P<leadspaces>\s+)(?P<value>.+?))?\s*$#u', $this->currentLine, $values)) {
                if ($context && 'mapping' == $context) {
                    throw new ParseException('You cannot define a sequence item when in a mapping');
                }
                $context = 'sequence';

                if (isset($values['value']) && preg_match('#^&(?P<ref>[^ ]+) *(?P<value>.*)#u', $values['value'], $matches)) {
                    $isRef = $matches['ref'];
                    $values['value'] = $matches['value'];
                }

                // array
                if (!isset($values['value']) || '' == trim($values['value'], ' ') || 0 === strpos(ltrim($values['value'], ' '), '#')) {
                    $c = $this->getRealCurrentLineNb() + 1;
                    $parser = new Parser($c);
                    $parser->refs = & $this->refs;
                    $data[] = $parser->parse($this->getNextEmbedBlock(null, true), $exceptionOnInvalidType, $objectSupport, $objectForMap);
                } else {
                    if (isset($values['leadspaces'])
                        && preg_match('#^(?P<key>'.Inline::REGEX_QUOTED_STRING.'|[^ \'"\{\[].*?) *\:(\s+(?P<value>.+?))?\s*$#u', $values['value'], $matches)
                    ) {
                        // this is a compact notation element, add to next block and parse
                        $c = $this->getRealCurrentLineNb();
                        $parser = new Parser($c);
                        $parser->refs = & $this->refs;

                        $block = $values['value'];
                        if ($this->isNextLineIndented()) {
                            $block .= "\n".$this->getNextEmbedBlock($this->getCurrentLineIndentation() + strlen($values['leadspaces']) + 1);
                        }

                        $data[] = $parser->parse($block, $exceptionOnInvalidType, $objectSupport, $objectForMap);
                    } else {
                        $data[] = $this->parseValue($values['value'], $exceptionOnInvalidType, $objectSupport, $objectForMap);
                    }
                }
            } elseif (preg_match('#^(?P<key>'.Inline::REGEX_QUOTED_STRING.'|[^ \'"\[\{].*?) *\:(\s+(?P<value>.+?))?\s*$#u', $this->currentLine, $values) && (false === strpos($values['key'], ' #') || in_array($values['key'][0], array('"', "'")))) {
                if ($context && 'sequence' == $context) {
                    throw new ParseException('You cannot define a mapping item when in a sequence');
                }
                $context = 'mapping';

                // force correct settings
                Inline::parse(null, $exceptionOnInvalidType, $objectSupport, $objectForMap, $this->refs);
                try {
                    $key = Inline::parseScalar($values['key']);
                } catch (ParseException $e) {
                    $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                    $e->setSnippet($this->currentLine);

                    throw $e;
                }

                if ('<<' === $key) {
                    $mergeNode = true;
                    $allowOverwrite = true;
                    if (isset($values['value']) && 0 === strpos($values['value'], '*')) {
                        $refName = substr($values['value'], 1);
                        if (!array_key_exists($refName, $this->refs)) {
                            throw new ParseException(sprintf('Reference "%s" does not exist.', $refName), $this->getRealCurrentLineNb() + 1, $this->currentLine);
                        }

                        $refValue = $this->refs[$refName];

                        if (!is_array($refValue)) {
                            throw new ParseException('YAML merge keys used with a scalar value instead of an array.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                        }

                        foreach ($refValue as $key => $value) {
                            if (!isset($data[$key])) {
                                $data[$key] = $value;
                            }
                        }
                    } else {
                        if (isset($values['value']) && $values['value'] !== '') {
                            $value = $values['value'];
                        } else {
                            $value = $this->getNextEmbedBlock();
                        }
                        $c = $this->getRealCurrentLineNb() + 1;
                        $parser = new Parser($c);
                        $parser->refs = & $this->refs;
                        $parsed = $parser->parse($value, $exceptionOnInvalidType, $objectSupport, $objectForMap);

                        if (!is_array($parsed)) {
                            throw new ParseException('YAML merge keys used with a scalar value instead of an array.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                        }

                        if (isset($parsed[0])) {
                            // If the value associated with the merge key is a sequence, then this sequence is expected to contain mapping nodes
                            // and each of these nodes is merged in turn according to its order in the sequence. Keys in mapping nodes earlier
                            // in the sequence override keys specified in later mapping nodes.
                            foreach ($parsed as $parsedItem) {
                                if (!is_array($parsedItem)) {
                                    throw new ParseException('Merge items must be arrays.', $this->getRealCurrentLineNb() + 1, $parsedItem);
                                }

                                foreach ($parsedItem as $key => $value) {
                                    if (!isset($data[$key])) {
                                        $data[$key] = $value;
                                    }
                                }
                            }
                        } else {
                            // If the value associated with the key is a single mapping node, each of its key/value pairs is inserted into the
                            // current mapping, unless the key already exists in it.
                            foreach ($parsed as $key => $value) {
                                if (!isset($data[$key])) {
                                    $data[$key] = $value;
                                }
                            }
                        }
                    }
                } elseif (isset($values['value']) && preg_match('#^&(?P<ref>[^ ]+) *(?P<value>.*)#u', $values['value'], $matches)) {
                    $isRef = $matches['ref'];
                    $values['value'] = $matches['value'];
                }

                if ($mergeNode) {
                    // Merge keys
                } elseif (!isset($values['value']) || '' == trim($values['value'], ' ') || 0 === strpos(ltrim($values['value'], ' '), '#')) {
                    // hash
                    // if next line is less indented or equal, then it means that the current value is null
                    if (!$this->isNextLineIndented() && !$this->isNextLineUnIndentedCollection()) {
                        // Spec: Keys MUST be unique; first one wins.
                        // But overwriting is allowed when a merge node is used in current block.
                        if ($allowOverwrite || !isset($data[$key])) {
                            $data[$key] = null;
                        }
                    } else {
                        $c = $this->getRealCurrentLineNb() + 1;
                        $parser = new Parser($c);
                        $parser->refs = & $this->refs;
                        $value = $parser->parse($this->getNextEmbedBlock(), $exceptionOnInvalidType, $objectSupport, $objectForMap);
                        // Spec: Keys MUST be unique; first one wins.
                        // But overwriting is allowed when a merge node is used in current block.
                        if ($allowOverwrite || !isset($data[$key])) {
                            $data[$key] = $value;
                        }
                    }
                } else {
                    $value = $this->parseValue($values['value'], $exceptionOnInvalidType, $objectSupport, $objectForMap);
                    // Spec: Keys MUST be unique; first one wins.
                    // But overwriting is allowed when a merge node is used in current block.
                    if ($allowOverwrite || !isset($data[$key])) {
                        $data[$key] = $value;
                    }
                }
            } else {
                // multiple documents are not supported
                if ('---' === $this->currentLine) {
                    throw new ParseException('Multiple documents are not supported.');
                }

                // 1-liner optionally followed by newline(s)
                if ($this->lines[0] === trim($value)) {
                    try {
                        $value = Inline::parse($this->lines[0], $exceptionOnInvalidType, $objectSupport, $objectForMap, $this->refs);
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
     * @return int The current line number
     */
    private function getRealCurrentLineNb()
    {
        return $this->currentLineNb + $this->offset;
    }

    /**
     * Returns the current line indentation.
     *
     * @return int The current line indentation
     */
    private function getCurrentLineIndentation()
    {
        return strlen($this->currentLine) - strlen(ltrim($this->currentLine, ' '));
    }

    /**
     * Returns the next embed block of YAML.
     *
     * @param int  $indentation The indent level at which the block is to be read, or null for default
     * @param bool $inSequence  True if the enclosing data structure is a sequence
     *
     * @return string A YAML string
     *
     * @throws ParseException When indentation problem are detected
     */
    private function getNextEmbedBlock($indentation = null, $inSequence = false)
    {
        $oldLineIndentation = $this->getCurrentLineIndentation();

        if (!$this->moveToNextLine()) {
            return;
        }

        if (null === $indentation) {
            $newIndent = $this->getCurrentLineIndentation();

            $unindentedEmbedBlock = $this->isStringUnIndentedCollectionItem($this->currentLine);

            if (!$this->isCurrentLineEmpty() && 0 === $newIndent && !$unindentedEmbedBlock) {
                throw new ParseException('Indentation problem.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
            }
        } else {
            $newIndent = $indentation;
        }

        $data = array();
        if ($this->getCurrentLineIndentation() >= $newIndent) {
            $data[] = substr($this->currentLine, $newIndent);
        } else {
            $this->moveToPreviousLine();

            return;
        }

        if ($inSequence && $oldLineIndentation === $newIndent && '-' === $data[0][0]) {
            // the previous line contained a dash but no item content, this line is a sequence item with the same indentation
            // and therefore no nested list or mapping
            $this->moveToPreviousLine();

            return;
        }

        $isItUnindentedCollection = $this->isStringUnIndentedCollectionItem($this->currentLine);

        // Comments must not be removed inside a string block (ie. after a line ending with "|")
        $removeCommentsPattern = '~'.self::FOLDED_SCALAR_PATTERN.'$~';
        $removeComments = !preg_match($removeCommentsPattern, $this->currentLine);

        while ($this->moveToNextLine()) {
            $indent = $this->getCurrentLineIndentation();

            if ($indent === $newIndent) {
                $removeComments = !preg_match($removeCommentsPattern, $this->currentLine);
            }

            if ($isItUnindentedCollection && !$this->isStringUnIndentedCollectionItem($this->currentLine) && $newIndent === $indent) {
                $this->moveToPreviousLine();
                break;
            }

            if ($this->isCurrentLineBlank()) {
                $data[] = substr($this->currentLine, $newIndent);
                continue;
            }

            if ($removeComments && $this->isCurrentLineComment()) {
                continue;
            }

            if ($indent >= $newIndent) {
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
     * @return bool
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
     * @param string $value                  A YAML value
     * @param bool   $exceptionOnInvalidType True if an exception must be thrown on invalid types false otherwise
     * @param bool   $objectSupport          True if object support is enabled, false otherwise
     * @param bool   $objectForMap           true if maps should return a stdClass instead of array()
     *
     * @return mixed A PHP value
     *
     * @throws ParseException When reference does not exist
     */
    private function parseValue($value, $exceptionOnInvalidType, $objectSupport, $objectForMap)
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

        if (preg_match('/^'.self::FOLDED_SCALAR_PATTERN.'$/', $value, $matches)) {
            $modifiers = isset($matches['modifiers']) ? $matches['modifiers'] : '';

            return $this->parseFoldedScalar($matches['separator'], preg_replace('#\d+#', '', $modifiers), (int) abs($modifiers));
        }

        try {
            return Inline::parse($value, $exceptionOnInvalidType, $objectSupport, $objectForMap, $this->refs);
        } catch (ParseException $e) {
            $e->setParsedLine($this->getRealCurrentLineNb() + 1);
            $e->setSnippet($this->currentLine);

            throw $e;
        }
    }

    /**
     * Parses a folded scalar.
     *
     * @param string $separator   The separator that was used to begin this folded scalar (| or >)
     * @param string $indicator   The indicator that was used to begin this folded scalar (+ or -)
     * @param int    $indentation The indentation that was used to begin this folded scalar
     *
     * @return string The text value
     */
    private function parseFoldedScalar($separator, $indicator = '', $indentation = 0)
    {
        $notEOF = $this->moveToNextLine();
        if (!$notEOF) {
            return '';
        }

        $isCurrentLineBlank = $this->isCurrentLineBlank();
        $text = '';

        // leading blank lines are consumed before determining indentation
        while ($notEOF && $isCurrentLineBlank) {
            // newline only if not EOF
            if ($notEOF = $this->moveToNextLine()) {
                $text .= "\n";
                $isCurrentLineBlank = $this->isCurrentLineBlank();
            }
        }

        // determine indentation if not specified
        if (0 === $indentation) {
            if (preg_match('/^ +/', $this->currentLine, $matches)) {
                $indentation = strlen($matches[0]);
            }
        }

        if ($indentation > 0) {
            $pattern = sprintf('/^ {%d}(.*)$/', $indentation);

            while (
                $notEOF && (
                    $isCurrentLineBlank ||
                    preg_match($pattern, $this->currentLine, $matches)
                )
            ) {
                if ($isCurrentLineBlank) {
                    $text .= substr($this->currentLine, $indentation);
                } else {
                    $text .= $matches[1];
                }

                // newline only if not EOF
                if ($notEOF = $this->moveToNextLine()) {
                    $text .= "\n";
                    $isCurrentLineBlank = $this->isCurrentLineBlank();
                }
            }
        } elseif ($notEOF) {
            $text .= "\n";
        }

        if ($notEOF) {
            $this->moveToPreviousLine();
        }

        // replace all non-trailing single newlines with spaces in folded blocks
        if ('>' === $separator) {
            preg_match('/(\n*)$/', $text, $matches);
            $text = preg_replace('/(?<!\n)\n(?!\n)/', ' ', rtrim($text, "\n"));
            $text .= $matches[1];
        }

        // deal with trailing newlines as indicated
        if ('' === $indicator) {
            $text = preg_replace('/\n+$/s', "\n", $text);
        } elseif ('-' === $indicator) {
            $text = preg_replace('/\n+$/s', '', $text);
        }

        return $text;
    }

    /**
     * Returns true if the next line is indented.
     *
     * @return bool Returns true if the next line is indented, false otherwise
     */
    private function isNextLineIndented()
    {
        $currentIndentation = $this->getCurrentLineIndentation();
        $EOF = !$this->moveToNextLine();

        while (!$EOF && $this->isCurrentLineEmpty()) {
            $EOF = !$this->moveToNextLine();
        }

        if ($EOF) {
            return false;
        }

        $ret = false;
        if ($this->getCurrentLineIndentation() > $currentIndentation) {
            $ret = true;
        }

        $this->moveToPreviousLine();

        return $ret;
    }

    /**
     * Returns true if the current line is blank or if it is a comment line.
     *
     * @return bool Returns true if the current line is empty or if it is a comment line, false otherwise
     */
    private function isCurrentLineEmpty()
    {
        return $this->isCurrentLineBlank() || $this->isCurrentLineComment();
    }

    /**
     * Returns true if the current line is blank.
     *
     * @return bool Returns true if the current line is blank, false otherwise
     */
    private function isCurrentLineBlank()
    {
        return '' == trim($this->currentLine, ' ');
    }

    /**
     * Returns true if the current line is a comment line.
     *
     * @return bool Returns true if the current line is a comment line, false otherwise
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
        $value = preg_replace('#^\%YAML[: ][\d\.]+.*\n#u', '', $value, -1, $count);
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

    /**
     * Returns true if the next line starts unindented collection.
     *
     * @return bool Returns true if the next line starts unindented collection, false otherwise
     */
    private function isNextLineUnIndentedCollection()
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
        if (
            $this->getCurrentLineIndentation() == $currentIndentation
            &&
            $this->isStringUnIndentedCollectionItem($this->currentLine)
        ) {
            $ret = true;
        }

        $this->moveToPreviousLine();

        return $ret;
    }

    /**
     * Returns true if the string is un-indented collection item.
     *
     * @return bool Returns true if the string is un-indented collection item, false otherwise
     */
    private function isStringUnIndentedCollectionItem()
    {
        return (0 === strpos($this->currentLine, '- '));
    }
}
