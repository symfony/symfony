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
    const TAG_PATTERN = '((?P<tag>![\w!.\/:-]+) +)?';
    const BLOCK_SCALAR_HEADER_PATTERN = '(?P<separator>\||>)(?P<modifiers>\+|\-|\d+|\+\d+|\-\d+|\d+\+|\d+\-)?(?P<comments> +#.*)?';

    private $offset = 0;
    private $totalNumberOfLines;
    private $lines = array();
    private $currentLineNb = -1;
    private $currentLine = '';
    private $refs = array();
    private $skippedLineNumbers = array();
    private $locallySkippedLineNumbers = array();

    /**
     * Constructor.
     *
     * @param int      $offset             The offset of YAML document (used for line numbers in error messages)
     * @param int|null $totalNumberOfLines The overall number of lines being parsed
     * @param int[]    $skippedLineNumbers Number of comment lines that have been skipped by the parser
     */
    public function __construct($offset = 0, $totalNumberOfLines = null, array $skippedLineNumbers = array())
    {
        $this->offset = $offset;
        $this->totalNumberOfLines = $totalNumberOfLines;
        $this->skippedLineNumbers = $skippedLineNumbers;
    }

    /**
     * Parses a YAML string to a PHP value.
     *
     * @param string $value A YAML string
     * @param int    $flags A bit field of PARSE_* constants to customize the YAML parser behavior
     *
     * @return mixed A PHP value
     *
     * @throws ParseException If the YAML is not valid
     */
    public function parse($value, $flags = 0)
    {
        if (is_bool($flags)) {
            @trigger_error('Passing a boolean flag to toggle exception handling is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE flag instead.', E_USER_DEPRECATED);

            if ($flags) {
                $flags = Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE;
            } else {
                $flags = 0;
            }
        }

        if (func_num_args() >= 3) {
            @trigger_error('Passing a boolean flag to toggle object support is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_OBJECT flag instead.', E_USER_DEPRECATED);

            if (func_get_arg(2)) {
                $flags |= Yaml::PARSE_OBJECT;
            }
        }

        if (func_num_args() >= 4) {
            @trigger_error('Passing a boolean flag to toggle object for map support is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_OBJECT_FOR_MAP flag instead.', E_USER_DEPRECATED);

            if (func_get_arg(3)) {
                $flags |= Yaml::PARSE_OBJECT_FOR_MAP;
            }
        }

        if (!preg_match('//u', $value)) {
            throw new ParseException('The YAML value does not appear to be valid UTF-8.');
        }
        $this->currentLineNb = -1;
        $this->currentLine = '';
        $value = $this->cleanup($value);
        $this->lines = explode("\n", $value);

        if (null === $this->totalNumberOfLines) {
            $this->totalNumberOfLines = count($this->lines);
        }

        if (2 /* MB_OVERLOAD_STRING */ & (int) ini_get('mbstring.func_overload')) {
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
                    throw new ParseException('You cannot define a sequence item when in a mapping', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                }
                $context = 'sequence';

                if (isset($values['value']) && preg_match('#^&(?P<ref>[^ ]+) *(?P<value>.*)#u', $values['value'], $matches)) {
                    $isRef = $matches['ref'];
                    $values['value'] = $matches['value'];
                }

                // array
                if (!isset($values['value']) || '' == trim($values['value'], ' ') || 0 === strpos(ltrim($values['value'], ' '), '#')) {
                    $data[] = $this->parseBlock($this->getRealCurrentLineNb() + 1, $this->getNextEmbedBlock(null, true), $flags);
                } else {
                    if (isset($values['leadspaces'])
                        && preg_match('#^(?P<key>'.Inline::REGEX_QUOTED_STRING.'|[^ \'"\{\[].*?) *\:(\s+(?P<value>.+?))?\s*$#u', $values['value'], $matches)
                    ) {
                        // this is a compact notation element, add to next block and parse
                        $block = $values['value'];
                        if ($this->isNextLineIndented()) {
                            $block .= "\n".$this->getNextEmbedBlock($this->getCurrentLineIndentation() + strlen($values['leadspaces']) + 1);
                        }

                        $data[] = $this->parseBlock($this->getRealCurrentLineNb(), $block, $flags);
                    } else {
                        $data[] = $this->parseValue($values['value'], $flags, $context);
                    }
                }
                if ($isRef) {
                    $this->refs[$isRef] = end($data);
                }
            } elseif (preg_match('#^(?P<key>'.Inline::REGEX_QUOTED_STRING.'|[^ \'"\[\{].*?) *\:(\s+(?P<value>.+?))?\s*$#u', $this->currentLine, $values) && (false === strpos($values['key'], ' #') || in_array($values['key'][0], array('"', "'")))) {
                if ($context && 'sequence' == $context) {
                    throw new ParseException('You cannot define a mapping item when in a sequence', $this->currentLineNb + 1, $this->currentLine);
                }
                $context = 'mapping';

                // force correct settings
                Inline::parse(null, $flags, $this->refs);
                try {
                    $key = Inline::parseScalar($values['key']);
                } catch (ParseException $e) {
                    $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                    $e->setSnippet($this->currentLine);

                    throw $e;
                }

                // Convert float keys to strings, to avoid being converted to integers by PHP
                if (is_float($key)) {
                    $key = (string) $key;
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
                        $parsed = $this->parseBlock($this->getRealCurrentLineNb() + 1, $value, $flags);

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
                        $value = $this->parseBlock($this->getRealCurrentLineNb() + 1, $this->getNextEmbedBlock(), $flags);
                        // Spec: Keys MUST be unique; first one wins.
                        // But overwriting is allowed when a merge node is used in current block.
                        if ($allowOverwrite || !isset($data[$key])) {
                            $data[$key] = $value;
                        }
                    }
                } else {
                    $value = $this->parseValue($values['value'], $flags, $context);
                    // Spec: Keys MUST be unique; first one wins.
                    // But overwriting is allowed when a merge node is used in current block.
                    if ($allowOverwrite || !isset($data[$key])) {
                        $data[$key] = $value;
                    }
                }
                if ($isRef) {
                    $this->refs[$isRef] = $data[$key];
                }
            } else {
                // multiple documents are not supported
                if ('---' === $this->currentLine) {
                    throw new ParseException('Multiple documents are not supported.', $this->currentLineNb + 1, $this->currentLine);
                }

                // 1-liner optionally followed by newline(s)
                if (is_string($value) && $this->lines[0] === trim($value)) {
                    try {
                        $value = Inline::parse($this->lines[0], $flags, $this->refs);
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
        }

        if (isset($mbEncoding)) {
            mb_internal_encoding($mbEncoding);
        }

        if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && !is_object($data) && 'mapping' === $context) {
            $object = new \stdClass();

            foreach ($data as $key => $value) {
                $object->$key = $value;
            }

            $data = $object;
        }

        return empty($data) ? null : $data;
    }

    private function parseBlock($offset, $yaml, $flags)
    {
        $skippedLineNumbers = $this->skippedLineNumbers;

        foreach ($this->locallySkippedLineNumbers as $lineNumber) {
            if ($lineNumber < $offset) {
                continue;
            }

            $skippedLineNumbers[] = $lineNumber;
        }

        $parser = new self($offset, $this->totalNumberOfLines, $skippedLineNumbers);
        $parser->refs = &$this->refs;

        return $parser->parse($yaml, $flags);
    }

    /**
     * Returns the current line number (takes the offset into account).
     *
     * @return int The current line number
     */
    private function getRealCurrentLineNb()
    {
        $realCurrentLineNumber = $this->currentLineNb + $this->offset;

        foreach ($this->skippedLineNumbers as $skippedLineNumber) {
            if ($skippedLineNumber > $realCurrentLineNumber) {
                break;
            }

            ++$realCurrentLineNumber;
        }

        return $realCurrentLineNumber;
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
        $blockScalarIndentations = array();

        if ($this->isBlockScalarHeader()) {
            $blockScalarIndentations[] = $this->getCurrentLineIndentation();
        }

        if (!$this->moveToNextLine()) {
            return;
        }

        if (null === $indentation) {
            $newIndent = $this->getCurrentLineIndentation();

            $unindentedEmbedBlock = $this->isStringUnIndentedCollectionItem();

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

        if ($inSequence && $oldLineIndentation === $newIndent && isset($data[0][0]) && '-' === $data[0][0]) {
            // the previous line contained a dash but no item content, this line is a sequence item with the same indentation
            // and therefore no nested list or mapping
            $this->moveToPreviousLine();

            return;
        }

        $isItUnindentedCollection = $this->isStringUnIndentedCollectionItem();

        if (empty($blockScalarIndentations) && $this->isBlockScalarHeader()) {
            $blockScalarIndentations[] = $this->getCurrentLineIndentation();
        }

        $previousLineIndentation = $this->getCurrentLineIndentation();

        while ($this->moveToNextLine()) {
            $indent = $this->getCurrentLineIndentation();

            // terminate all block scalars that are more indented than the current line
            if (!empty($blockScalarIndentations) && $indent < $previousLineIndentation && trim($this->currentLine) !== '') {
                foreach ($blockScalarIndentations as $key => $blockScalarIndentation) {
                    if ($blockScalarIndentation >= $this->getCurrentLineIndentation()) {
                        unset($blockScalarIndentations[$key]);
                    }
                }
            }

            if (empty($blockScalarIndentations) && !$this->isCurrentLineComment() && $this->isBlockScalarHeader()) {
                $blockScalarIndentations[] = $this->getCurrentLineIndentation();
            }

            $previousLineIndentation = $indent;

            if ($isItUnindentedCollection && !$this->isStringUnIndentedCollectionItem() && $newIndent === $indent) {
                $this->moveToPreviousLine();
                break;
            }

            if ($this->isCurrentLineBlank()) {
                $data[] = substr($this->currentLine, $newIndent);
                continue;
            }

            // we ignore "comment" lines only when we are not inside a scalar block
            if (empty($blockScalarIndentations) && $this->isCurrentLineComment()) {
                // remember ignored comment lines (they are used later in nested
                // parser calls to determine real line numbers)
                //
                // CAUTION: beware to not populate the global property here as it
                // will otherwise influence the getRealCurrentLineNb() call here
                // for consecutive comment lines and subsequent embedded blocks
                $this->locallySkippedLineNumbers[] = $this->getRealCurrentLineNb();

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
     *
     * @return bool
     */
    private function moveToPreviousLine()
    {
        if ($this->currentLineNb < 1) {
            return false;
        }

        $this->currentLine = $this->lines[--$this->currentLineNb];

        return true;
    }

    /**
     * Parses a YAML value.
     *
     * @param string $value   A YAML value
     * @param int    $flags   A bit field of PARSE_* constants to customize the YAML parser behavior
     * @param string $context The parser context (either sequence or mapping)
     *
     * @return mixed A PHP value
     *
     * @throws ParseException When reference does not exist
     */
    private function parseValue($value, $flags, $context)
    {
        if (0 === strpos($value, '*')) {
            if (false !== $pos = strpos($value, '#')) {
                $value = substr($value, 1, $pos - 2);
            } else {
                $value = substr($value, 1);
            }

            if (!array_key_exists($value, $this->refs)) {
                throw new ParseException(sprintf('Reference "%s" does not exist.', $value), $this->currentLineNb + 1, $this->currentLine);
            }

            return $this->refs[$value];
        }

        if (preg_match('/^'.self::TAG_PATTERN.self::BLOCK_SCALAR_HEADER_PATTERN.'$/', $value, $matches)) {
            $modifiers = isset($matches['modifiers']) ? $matches['modifiers'] : '';

            $data = $this->parseBlockScalar($matches['separator'], preg_replace('#\d+#', '', $modifiers), (int) abs($modifiers));

            if (isset($matches['tag']) && '!!binary' === $matches['tag']) {
                return Inline::evaluateBinaryScalar($data);
            }

            return $data;
        }

        try {
            $parsedValue = Inline::parse($value, $flags, $this->refs);

            if ('mapping' === $context && is_string($parsedValue) && '"' !== $value[0] && "'" !== $value[0] && '[' !== $value[0] && '{' !== $value[0] && '!' !== $value[0] && false !== strpos($parsedValue, ': ')) {
                throw new ParseException('A colon cannot be used in an unquoted mapping value.');
            }

            return $parsedValue;
        } catch (ParseException $e) {
            $e->setParsedLine($this->getRealCurrentLineNb() + 1);
            $e->setSnippet($this->currentLine);

            throw $e;
        }
    }

    /**
     * Parses a block scalar.
     *
     * @param string $style       The style indicator that was used to begin this block scalar (| or >)
     * @param string $chomping    The chomping indicator that was used to begin this block scalar (+ or -)
     * @param int    $indentation The indentation indicator that was used to begin this block scalar
     *
     * @return string The text value
     */
    private function parseBlockScalar($style, $chomping = '', $indentation = 0)
    {
        $notEOF = $this->moveToNextLine();
        if (!$notEOF) {
            return '';
        }

        $isCurrentLineBlank = $this->isCurrentLineBlank();
        $blockLines = array();

        // leading blank lines are consumed before determining indentation
        while ($notEOF && $isCurrentLineBlank) {
            // newline only if not EOF
            if ($notEOF = $this->moveToNextLine()) {
                $blockLines[] = '';
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
                if ($isCurrentLineBlank && strlen($this->currentLine) > $indentation) {
                    $blockLines[] = substr($this->currentLine, $indentation);
                } elseif ($isCurrentLineBlank) {
                    $blockLines[] = '';
                } else {
                    $blockLines[] = $matches[1];
                }

                // newline only if not EOF
                if ($notEOF = $this->moveToNextLine()) {
                    $isCurrentLineBlank = $this->isCurrentLineBlank();
                }
            }
        } elseif ($notEOF) {
            $blockLines[] = '';
        }

        if ($notEOF) {
            $blockLines[] = '';
            $this->moveToPreviousLine();
        } elseif (!$notEOF && !$this->isCurrentLineLastLineInDocument()) {
            $blockLines[] = '';
        }

        // folded style
        if ('>' === $style) {
            $text = '';
            $previousLineIndented = false;
            $previousLineBlank = false;

            for ($i = 0; $i < count($blockLines); ++$i) {
                if ('' === $blockLines[$i]) {
                    $text .= "\n";
                    $previousLineIndented = false;
                    $previousLineBlank = true;
                } elseif (' ' === $blockLines[$i][0]) {
                    $text .= "\n".$blockLines[$i];
                    $previousLineIndented = true;
                    $previousLineBlank = false;
                } elseif ($previousLineIndented) {
                    $text .= "\n".$blockLines[$i];
                    $previousLineIndented = false;
                    $previousLineBlank = false;
                } elseif ($previousLineBlank || 0 === $i) {
                    $text .= $blockLines[$i];
                    $previousLineIndented = false;
                    $previousLineBlank = false;
                } else {
                    $text .= ' '.$blockLines[$i];
                    $previousLineIndented = false;
                    $previousLineBlank = false;
                }
            }
        } else {
            $text = implode("\n", $blockLines);
        }

        // deal with trailing newlines
        if ('' === $chomping) {
            $text = preg_replace('/\n+$/', "\n", $text);
        } elseif ('-' === $chomping) {
            $text = preg_replace('/\n+$/', '', $text);
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

        return '' !== $ltrimmedLine && $ltrimmedLine[0] === '#';
    }

    private function isCurrentLineLastLineInDocument()
    {
        return ($this->offset + $this->currentLineNb) >= ($this->totalNumberOfLines - 1);
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
            $value = preg_replace('#\.\.\.\s*$#', '', $value);
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
            $this->isStringUnIndentedCollectionItem()
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
        return '-' === rtrim($this->currentLine) || 0 === strpos($this->currentLine, '- ');
    }

    /**
     * Tests whether or not the current line is the header of a block scalar.
     *
     * @return bool
     */
    private function isBlockScalarHeader()
    {
        return (bool) preg_match('~'.self::BLOCK_SCALAR_HEADER_PATTERN.'$~', $this->currentLine);
    }
}
