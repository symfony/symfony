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
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Parser parses YAML strings to convert them to PHP values.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Parser
{
    public const TAG_PATTERN = '(?P<tag>![\w!.\/:-]+)';
    public const BLOCK_SCALAR_HEADER_PATTERN = '(?P<separator>\||>)(?P<modifiers>\+|\-|\d+|\+\d+|\-\d+|\d+\+|\d+\-)?(?P<comments>[ \t]+#.*)?';
    public const REFERENCE_PATTERN = '#^&(?P<ref>[^ ]++) *+(?P<value>.*)#';
    public const DEFAULT_MAX_NESTING_LEVEL = 128;
    public const DEFAULT_MAX_ALIASES_FOR_COLLECTIONS = 128;

    private ?string $filename = null;
    private int $offset = 0;
    private int $numberOfParsedLines = 0;
    private array $lines = [];
    private int $currentLineNb = -1;
    private string $currentLine = '';
    private array $refs = [];
    private array $refsBeingParsed = [];
    private ?ParserState $state = null;
    private int $parentIndentation = -1;
    private int $blockIndentation = 0;
    private bool $blockInUnindentedCollection = false;
    private int $blockStartLineNb = 0;
    private bool $allowFirstLineDedent = false;
    private ?string $firstLineOverride = null;

    public function __construct(int $maxNestingLevel = self::DEFAULT_MAX_NESTING_LEVEL, int $maxAliasesForCollections = self::DEFAULT_MAX_ALIASES_FOR_COLLECTIONS)
    {
        if ($maxNestingLevel < 1) {
            throw new \InvalidArgumentException('The maximum nesting depth must be greater than 0.');
        }

        if ($maxAliasesForCollections < 0) {
            throw new \InvalidArgumentException('The maximum number of collection aliases must be greater than or equal to 0.');
        }

        $this->getState()->maxNestingLevel = $maxNestingLevel;
        $this->getState()->maxAliasesForCollections = $maxAliasesForCollections;
    }

    /**
     * Parses a YAML file into a PHP value.
     *
     * @param string                     $filename The path to the YAML file to be parsed
     * @param int-mask-of<Yaml::PARSE_*> $flags    A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
     *
     * @throws ParseException If the file could not be read or the YAML is not valid
     */
    public function parseFile(string $filename, int $flags = 0): mixed
    {
        if (!is_file($filename)) {
            throw new ParseException(\sprintf('File "%s" does not exist.', $filename));
        }

        if (!is_readable($filename)) {
            throw new ParseException(\sprintf('File "%s" cannot be read.', $filename));
        }

        $this->filename = $filename;

        try {
            return $this->parse(file_get_contents($filename), $flags);
        } finally {
            $this->filename = null;
        }
    }

    /**
     * Parses a YAML string to a PHP value.
     *
     * @param string                     $value A YAML string
     * @param int-mask-of<Yaml::PARSE_*> $flags A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
     *
     * @throws ParseException If the YAML is not valid
     */
    public function parse(string $value, int $flags = 0): mixed
    {
        if (false === preg_match('//u', $value)) {
            throw new ParseException('The YAML value does not appear to be valid UTF-8.', -1, null, $this->filename);
        }

        $this->refs = [];
        $state = $this->getState();
        $state->reset();
        $state->aliasesEnabled = 0 === (Yaml::PARSE_EXCEPTION_ON_ALIAS & $flags);
        $state->maxAliasedNodeCount = min(\PHP_INT_MAX, $state->maxAliasesForCollections * \strlen($value));

        try {
            $value = $this->cleanup($value, $hasContentOnStartMarkerLine);
            $this->lines = explode("\n", $value);
            $this->numberOfParsedLines = \count($this->lines);

            return $this->doParseBlock($flags, 0, 0, -1, false, null, $hasContentOnStartMarkerLine, true);
        } finally {
            $this->refsBeingParsed = [];
            $this->offset = 0;
            $this->lines = [];
            $this->currentLineNb = -1;
            $this->currentLine = '';
            $this->numberOfParsedLines = 0;
            $this->refs = [];
            $this->parentIndentation = -1;
            $this->blockIndentation = 0;
            $this->blockInUnindentedCollection = false;
            $this->blockStartLineNb = 0;
            $this->allowFirstLineDedent = false;
            $this->firstLineOverride = null;
            $state->reset();
        }
    }

    private function getState(): ParserState
    {
        return $this->state ??= new ParserState();
    }

    private function doParseBlock(
        int $flags,
        int $startLineNb,
        int $blockIndentation,
        int $parentIndentation,
        bool $inUnindentedCollection = false,
        ?string $firstLineOverride = null,
        bool $hasContentOnStartMarkerLine = false,
        bool $isRootNode = false,
        ?string &$context = null,
        bool $allowFirstLineDedent = false,
    ): mixed {
        $prevParentIndentation = $this->parentIndentation;
        $prevBlockIndentation = $this->blockIndentation;
        $prevInUnindentedCollection = $this->blockInUnindentedCollection;
        $prevBlockStartLineNb = $this->blockStartLineNb;
        $prevAllowFirstLineDedent = $this->allowFirstLineDedent;
        $prevFirstLineOverride = $this->firstLineOverride;

        $this->parentIndentation = $parentIndentation;
        $this->blockIndentation = $blockIndentation;
        $this->blockInUnindentedCollection = $inUnindentedCollection;
        $this->blockStartLineNb = $startLineNb;
        $this->allowFirstLineDedent = $allowFirstLineDedent;
        $this->firstLineOverride = $firstLineOverride;
        $this->currentLineNb = $startLineNb - 1;

        try {
            if (!$this->moveToNextLine()) {
                return null;
            }

            $data = [];
            $context = null;
            $allowOverwrite = false;

            while ($this->isCurrentLineEmpty()) {
                if (!$this->moveToNextLine()) {
                    return null;
                }
            }

            if ($isRootNode && self::preg_match('/^(?:'.self::TAG_PATTERN.'[ \t]+)?'.self::BLOCK_SCALAR_HEADER_PATTERN.'$/', rtrim($this->currentLine)) && ($this->isNextLineIndented() || null === $this->getNextContentLineIndentation())) {
                return $this->parseRootBlockScalar($flags);
            }

            // Resolves the tag and returns if end of the document
            if (null !== $tag = $this->getLineTag($this->currentLine, $flags, false)) {
                do {
                    if (!$this->moveToNextLine()) {
                        return new TaggedValue($tag, '');
                    }
                } while ($this->isCurrentLineEmpty());
            }

            do {
                if ($this->isCurrentLineEmpty()) {
                    continue;
                }

                // tab?
                if ("\t" === $this->currentLine[0]) {
                    throw new ParseException('A YAML file cannot contain tabs as indentation.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                }

                Inline::initialize($flags, $this->getRealCurrentLineNb(), $this->filename);

                $isRef = $mergeNode = false;
                $nodeCount = $this->getState()->nodeCount;
                if ('-' === $this->currentLine[0] && self::preg_match('#^\-((?P<leadspaces>\s+)(?P<value>.+))?$#', rtrim($this->currentLine), $values)) {
                    if ($context && 'mapping' == $context) {
                        throw new ParseException('You cannot define a sequence item when in a mapping.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }
                    $context = 'sequence';

                    if ($hasContentOnStartMarkerLine && 0 === $this->currentLineNb) {
                        throw new ParseException('A block collection cannot start on the line of the document start marker (---).', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }

                    if (isset($values['value']) && '!' === $values['value'][0]) {
                        $values['value'] = $this->moveAnchorBeforeTag($values['value']);
                    }

                    if (isset($values['value']) && '&' === $values['value'][0] && self::preg_match(self::REFERENCE_PATTERN, $values['value'], $matches)) {
                        $isRef = $matches['ref'];
                        $this->refsBeingParsed[] = $isRef;
                        $values['value'] = $matches['value'];
                    }

                    if (isset($values['value'][1]) && '?' === $values['value'][0] && ' ' === $values['value'][1]) {
                        throw new ParseException('Complex mappings are not supported.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                    }

                    // array
                    if (isset($values['value']) && str_starts_with(ltrim($values['value'], ' '), '-')) {
                        // Inline first child
                        $sequenceIndentation = \strlen($values['leadspaces']) + 1;
                        $data[] = $this->parseChildBlockAtCurrentLine($flags, $this->blockIndentation + $sequenceIndentation, substr($this->currentLine, $sequenceIndentation));
                    } elseif (!isset($values['value']) || '' == trim($values['value'], ' ') || str_starts_with(ltrim($values['value'], ' '), '#')) {
                        $data[] = $this->parseNextChildBlock($flags, true);
                    } elseif (null !== $subTag = $this->getLineTag(ltrim($values['value'], ' '), $flags)) {
                        $data[] = new TaggedValue(
                            $subTag,
                            $this->parseNextChildBlock($flags, true)
                        );
                    } elseif (self::preg_match('/^'.self::TAG_PATTERN.'[ \t]+'.self::BLOCK_SCALAR_HEADER_PATTERN.'$/', $values['value'])) {
                        $data[] = $this->parseValue($values['value'], $flags, $context);
                    } else {
                        if (
                            isset($values['leadspaces'])
                            && (
                                '!' === $values['value'][0]
                                || self::preg_match('#^(?P<key>'.Inline::REGEX_QUOTED_STRING.'|[^ \'"\{\[](?:[^ :]++| ++(?!:(?:\s|$))|:(?!\s|$))*+) *+\:(\s++(?P<value>(?:\s*+\S)++))?\s*+$#', $this->trimTag($values['value']), $matches)
                            )
                        ) {
                            $data[] = $this->parseChildBlockAtCurrentLine($flags, $this->blockIndentation + \strlen($values['leadspaces']) + 1, $values['value']);
                        } else {
                            $data[] = $this->parseValue($values['value'], $flags, $context);
                        }
                    }
                    if (false !== $isRef) {
                        $this->refs[$isRef] = end($data);
                        $this->getState()->anchorNodeCounts[$isRef] = $this->getState()->nodeCount - $nodeCount;
                        array_pop($this->refsBeingParsed);
                    }
                } elseif (
                    self::preg_match('#^(?P<key>(?:![^\s]++\s++)?(?:'.Inline::REGEX_QUOTED_STRING.'|[^ \'"\[\{!](?:[^ :]++| ++(?!:(?:[ \t]|$))|:(?![ \t]|$))*+)|![^\s]++(?= +\:)) *+\:(( |\t)++(?P<value>.+))?$#', rtrim($this->currentLine), $values)
                    && (!str_contains($values['key'], ' #') || \in_array($values['key'][0], ['"', "'"], true))
                ) {
                    if ($context && 'sequence' == $context) {
                        throw new ParseException('You cannot define a mapping item when in a sequence.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }
                    $context = 'mapping';

                    if ($hasContentOnStartMarkerLine && 0 === $this->currentLineNb) {
                        throw new ParseException('A block collection cannot start on the line of the document start marker (---).', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }

                    if (isset($values['value'][0]) && '!' === $values['value'][0]) {
                        $values['value'] = $this->moveAnchorBeforeTag($values['value']);
                    }

                    try {
                        $key = str_starts_with($values['key'], '*') ? $this->parseValue($values['key'], $flags, $context) : Inline::parseScalar($values['key']);
                    } catch (ParseException $e) {
                        $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                        $e->setSnippet($this->currentLine);

                        throw $e;
                    }

                    if (!\is_string($key) && !\is_int($key)) {
                        throw new ParseException((is_numeric($key) ? 'Numeric' : 'Non-string').' keys are not supported. Quote your evaluable mapping keys instead.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                    }

                    if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && \is_string($key) && str_starts_with($key, "\0")) {
                        throw new ParseException('Mapping keys starting with a NUL byte are not supported with the "Yaml::PARSE_OBJECT_FOR_MAP" flag.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }

                    // Convert float keys to strings, to avoid being converted to integers by PHP
                    if (\is_float($key)) {
                        $key = (string) $key;
                    }

                    // Only a plain (unquoted) "<<" key is a merge key
                    $isMergeKey = '<<' === rtrim($values['key']);

                    if ($isMergeKey && (!isset($values['value']) || '&' !== $values['value'][0] || !self::preg_match('#^&(?P<ref>[^ ]+)#', $values['value'], $refMatches))) {
                        $mergeNode = true;
                        $allowOverwrite = true;
                        if (isset($values['value'][0]) && '*' === $values['value'][0]) {
                            $refName = substr(rtrim($values['value']), 1);

                            // remove comments
                            if (self::preg_match('/(?<![ \t])[ \t]++#/', $refName, $match, \PREG_OFFSET_CAPTURE)) {
                                $refName = substr($refName, 0, $match[0][1]);
                            }

                            if (!\array_key_exists($refName, $this->refs)) {
                                if (false !== $pos = array_search($refName, $this->refsBeingParsed, true)) {
                                    throw new ParseException(\sprintf('Circular reference [%s] detected for reference "%s".', implode(', ', array_merge(\array_slice($this->refsBeingParsed, $pos), [$refName])), $refName), $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                                }

                                throw new ParseException(\sprintf('Reference "%s" does not exist.', $refName), $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }

                            $refValue = $this->refs[$refName];

                            $this->getState()->countAlias($refName, $refValue, $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);

                            if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && $refValue instanceof \stdClass) {
                                $refValue = (array) $refValue;
                            }

                            if (!\is_array($refValue)) {
                                throw new ParseException('YAML merge keys used with a scalar value instead of an array.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }

                            $data += $refValue; // array union
                        } else {
                            $mergeLineNb = $this->getRealCurrentLineNb();
                            $mergeLine = $this->currentLine;
                            $childContext = null;

                            if (isset($values['value']) && '' !== $values['value']) {
                                $parsed = $this->parseInlineMergeValue($mergeLineNb, $values['value'], $flags, $childContext);
                            } else {
                                $parsed = $this->parseNextChildBlock($flags, false, $childContext);
                            }

                            if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && $parsed instanceof \stdClass) {
                                $parsed = (array) $parsed;
                            }

                            if (!\is_array($parsed)) {
                                throw new ParseException('YAML merge keys used with a scalar value instead of an array.', $this->getRealCurrentLineNb() + 1, $mergeLine, $this->filename);
                            }

                            if ('sequence' === $childContext) {
                                // If the value associated with the merge key is a sequence, then this sequence is expected to contain mapping nodes
                                // and each of these nodes is merged in turn according to its order in the sequence. Keys in mapping nodes earlier
                                // in the sequence override keys specified in later mapping nodes.
                                foreach ($parsed as $parsedItem) {
                                    if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && $parsedItem instanceof \stdClass) {
                                        $parsedItem = (array) $parsedItem;
                                    }

                                    if (!\is_array($parsedItem)) {
                                        throw new ParseException('Merge items must be arrays.', $mergeLineNb + 1, \is_scalar($parsedItem) ? $parsedItem : null, $this->filename);
                                    }

                                    $data += $parsedItem; // array union
                                }
                            } else {
                                // If the value associated with the key is a single mapping node, each of its key/value pairs is inserted into the
                                // current mapping, unless the key already exists in it.
                                $data += $parsed; // array union
                            }
                        }
                    } elseif (!$isMergeKey && isset($values['value']) && '&' === $values['value'][0] && self::preg_match(self::REFERENCE_PATTERN, $values['value'], $matches)) {
                        $isRef = $matches['ref'];
                        $this->refsBeingParsed[] = $isRef;
                        $values['value'] = $matches['value'];
                    }

                    $subTag = null;
                    if ($mergeNode) {
                        // Merge keys
                    } elseif (!isset($values['value']) || '' === $values['value'] || str_starts_with($values['value'], '#') || (null !== $subTag = $this->getLineTag($values['value'], $flags)) || $isMergeKey) {
                        // hash
                        // if next line is less indented or equal, then it means that the current value is null
                        $nextRelIndent = $this->getNextContentLineIndentation($nextLine, $nextLineNb);
                        $hasChildBlock = null !== $nextRelIndent && ($nextRelIndent > 0 || self::isUnIndentedCollectionItem($nextLine));

                        if (!$hasChildBlock) {
                            // Spec: Keys MUST be unique; first one wins.
                            // But overwriting is allowed when a merge node is used in current block.
                            if ($allowOverwrite || !isset($data[$key])) {
                                if (!$allowOverwrite && \array_key_exists($key, $data)) {
                                    throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), $this->getRealCurrentLineNb() + 1, $this->currentLine);
                                }

                                if (null !== $subTag) {
                                    $data[$key] = new TaggedValue($subTag, '');
                                } else {
                                    $data[$key] = null;
                                }
                            } else {
                                throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), $this->getRealCurrentLineNb() + 1, $this->currentLine);
                            }
                        } else {
                            // remember the parsed line number here in case we need it to provide some contexts in error messages below
                            $realCurrentLineNbKey = $this->getRealCurrentLineNb();
                            $keyLine = $this->currentLine;
                            if ($allowOverwrite || !isset($data[$key])) {
                                if (!$allowOverwrite && \array_key_exists($key, $data)) {
                                    throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), $realCurrentLineNbKey + 1, $keyLine);
                                }

                                $value = $this->parseChildBlockAtLine($flags, $nextLineNb, $this->blockIndentation + $nextRelIndent, 0 === $nextRelIndent);
                                if ($isMergeKey) {
                                    $this->refs[$refMatches['ref']] = $value;
                                    $this->getState()->anchorNodeCounts[$refMatches['ref']] = $this->getState()->nodeCount - $nodeCount;

                                    if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && $value instanceof \stdClass) {
                                        $value = (array) $value;
                                    }

                                    $data += $value;
                                } else {
                                    // Spec: Keys MUST be unique; first one wins.
                                    // But overwriting is allowed when a merge node is used in current block.
                                    if (null !== $subTag) {
                                        $data[$key] = new TaggedValue($subTag, $value);
                                    } else {
                                        $data[$key] = $value;
                                    }
                                }
                            } else {
                                throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), $realCurrentLineNbKey + 1, $keyLine);
                            }
                        }
                    } else {
                        $value = $this->parseValue(rtrim($values['value']), $flags, $context);
                        // Spec: Keys MUST be unique; first one wins.
                        // But overwriting is allowed when a merge node is used in current block.
                        if ($allowOverwrite || !isset($data[$key])) {
                            if (!$allowOverwrite && \array_key_exists($key, $data)) {
                                throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), $this->getRealCurrentLineNb() + 1, $this->currentLine);
                            }

                            $data[$key] = $value;
                        } else {
                            throw new ParseException(\sprintf('Duplicate key "%s" detected.', $key), $this->getRealCurrentLineNb() + 1, $this->currentLine);
                        }
                    }
                    if (false !== $isRef) {
                        $this->refs[$isRef] = $data[$key];
                        $this->getState()->anchorNodeCounts[$isRef] = $this->getState()->nodeCount - $nodeCount;
                        array_pop($this->refsBeingParsed);
                    }
                } elseif ('"' === $this->currentLine[0] || "'" === $this->currentLine[0]) {
                    if (null !== $context) {
                        throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }

                    try {
                        $parsed = Inline::parse($this->lexInlineQuotedString(), $flags, $this->refs, $this->state);
                        if (!$isRootNode) {
                            while ($this->moveToNextLine()) {
                            }
                        }

                        return $parsed;
                    } catch (ParseException $e) {
                        $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                        $e->setSnippet($this->currentLine);

                        throw $e;
                    }
                } elseif ('{' === $this->currentLine[0]) {
                    if (null !== $context) {
                        throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }

                    $context = 'mapping';

                    try {
                        $parsedMapping = Inline::parse($this->lexInlineMapping(), $flags, $this->refs, $this->state);

                        while ($this->moveToNextLine()) {
                            if (!$this->isCurrentLineEmpty()) {
                                throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }
                        }

                        return $parsedMapping;
                    } catch (ParseException $e) {
                        $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                        $e->setSnippet($this->currentLine);

                        throw $e;
                    }
                } elseif ('[' === $this->currentLine[0]) {
                    if (null !== $context) {
                        throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                    }

                    $context = 'sequence';

                    try {
                        $parsedSequence = Inline::parse($this->lexInlineSequence(), $flags, $this->refs, $this->state);

                        while ($this->moveToNextLine()) {
                            if (!$this->isCurrentLineEmpty()) {
                                throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                            }
                        }

                        return $parsedSequence;
                    } catch (ParseException $e) {
                        $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                        $e->setSnippet($this->currentLine);

                        throw $e;
                    }
                } else {
                    if (isset($this->currentLine[1]) && '?' === $this->currentLine[0] && ' ' === $this->currentLine[1]) {
                        throw new ParseException('Complex mappings are not supported.', $this->getRealCurrentLineNb() + 1, $this->currentLine);
                    }

                    $firstLineNb = $this->currentLineNb;
                    $firstLine = $this->currentLine;

                    if ($firstLineNb === $this->blockStartLineNb) {
                        if (trim($firstLine) === $firstLine && $this->hasOnlyEmptyRemainingLines()) {
                            try {
                                return Inline::parse($firstLine, $flags, $this->refs, $this->state);
                            } catch (ParseException $e) {
                                $e->setParsedLine($this->offset + $firstLineNb + 1);
                                $e->setSnippet($firstLine);

                                throw $e;
                            }
                        }

                        // try to parse the value as a multi-line string as a last resort
                        $this->currentLineNb = $firstLineNb;
                        $this->currentLine = $firstLine;
                        $previousLineWasNewline = false;
                        $previousLineWasTerminatedWithBackslash = false;
                        $value = '';

                        $line = $firstLine;
                        while (true) {
                            $trimmedLine = trim($line);
                            if ('#' !== ($trimmedLine[0] ?? '')) {
                                // If the indentation is not consistent at offset 0, it is to be considered as a ParseError
                                if ($isRootNode && 0 === $this->offset && isset($line[0]) && ' ' === $line[0]) {
                                    throw new ParseException('Unable to parse.', $this->offset + $firstLineNb + 1, $firstLine, $this->filename);
                                }

                                if (str_contains($line, ': ')) {
                                    throw new ParseException('Mapping values are not allowed in multi-line blocks.', $this->offset + $firstLineNb + 1, $firstLine, $this->filename);
                                }

                                if ('' === $trimmedLine) {
                                    $value .= "\n";
                                } elseif (!$previousLineWasNewline && !$previousLineWasTerminatedWithBackslash) {
                                    $value .= ' ';
                                }

                                if ('' !== $trimmedLine && str_ends_with($line, '\\')) {
                                    $value .= ltrim(substr($line, 0, -1));
                                } elseif ('' !== $trimmedLine) {
                                    $value .= $trimmedLine;
                                }

                                if ('' === $trimmedLine) {
                                    $previousLineWasNewline = true;
                                    $previousLineWasTerminatedWithBackslash = false;
                                } elseif (str_ends_with($line, '\\')) {
                                    $previousLineWasNewline = false;
                                    $previousLineWasTerminatedWithBackslash = true;
                                } else {
                                    $previousLineWasNewline = false;
                                    $previousLineWasTerminatedWithBackslash = false;
                                }
                            }

                            if (!$this->moveToNextLine()) {
                                break;
                            }

                            $line = $this->currentLine;
                        }

                        try {
                            return Inline::parse(trim($value), 0, $this->refs, $this->state);
                        } catch (ParseException) {
                            // fall-through to the ParseException thrown below
                        }

                        throw new ParseException('Unable to parse.', $this->offset + $firstLineNb + 1, $firstLine, $this->filename);
                    }

                    throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                }
            } while ($this->moveToNextLine());

            $this->getState()->nodeCount += \count($data);

            if (null !== $tag) {
                $data = new TaggedValue($tag, $data);
            }

            if (Yaml::PARSE_OBJECT_FOR_MAP & $flags && 'mapping' === $context && !\is_object($data)) {
                $object = new \stdClass();

                foreach ($data as $key => $value) {
                    $object->$key = $value;
                }

                $data = $object;
            }

            return $data ?: null;
        } finally {
            $this->parentIndentation = $prevParentIndentation;
            $this->blockIndentation = $prevBlockIndentation;
            $this->blockInUnindentedCollection = $prevInUnindentedCollection;
            $this->blockStartLineNb = $prevBlockStartLineNb;
            $this->allowFirstLineDedent = $prevAllowFirstLineDedent;
            $this->firstLineOverride = $prevFirstLineOverride;
        }
    }

    private function parseChildBlockAtCurrentLine(int $flags, int $blockIndentation, string $firstLineOverride): mixed
    {
        $this->getState()->enterNestingLevel($this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);

        try {
            return $this->doParseBlock($flags, $this->currentLineNb, $blockIndentation, $this->blockIndentation, false, $firstLineOverride, allowFirstLineDedent: true);
        } finally {
            $this->getState()->leaveNestingLevel();
        }
    }

    private function parseChildBlockAtLine(int $flags, int $startLineNb, int $blockIndentation, bool $inUnindentedCollection): mixed
    {
        $this->getState()->enterNestingLevel($this->getRealCurrentLineNb() + 2, $this->currentLine, $this->filename);

        try {
            return $this->doParseBlock($flags, $startLineNb, $blockIndentation, $this->blockIndentation, $inUnindentedCollection);
        } finally {
            $this->getState()->leaveNestingLevel();
        }
    }

    private function parseNextChildBlock(int $flags, bool $inSequence = false, ?string &$context = null): mixed
    {
        $this->getState()->enterNestingLevel($this->getRealCurrentLineNb() + 2, $this->currentLine, $this->filename);

        try {
            $nextLineNb = null;
            $nextRawIndent = null;

            for ($i = $this->currentLineNb + 1; $i < $this->numberOfParsedLines; ++$i) {
                $rawLine = $this->lines[$i];
                $indent = strspn($rawLine, ' ');
                $firstChar = $rawLine[$indent] ?? '';

                if ('' !== $firstChar && '#' !== $firstChar) {
                    $nextLineNb = $i;
                    $nextRawIndent = $indent;
                    break;
                }
            }

            if (null === $nextLineNb) {
                $this->currentLineNb = $this->numberOfParsedLines - 1;

                return null;
            }

            if ($nextRawIndent < $this->blockIndentation) {
                if ($nextRawIndent > $this->parentIndentation) {
                    throw new ParseException('Indentation problem.', $this->offset + $nextLineNb + 1, substr($this->lines[$nextLineNb], max(0, $this->parentIndentation)), $this->filename);
                }

                $this->currentLineNb = $nextLineNb - 1;

                return null;
            }

            if ($nextRawIndent === $this->blockIndentation) {
                if (self::isUnIndentedCollectionItemAt($this->lines[$nextLineNb], $nextRawIndent)) {
                    if ($inSequence) {
                        $this->currentLineNb = $nextLineNb - 1;

                        return null;
                    }

                    return $this->doParseBlock($flags, $nextLineNb, $nextRawIndent, $this->blockIndentation, true, null, false, false, $context);
                }

                if ($inSequence) {
                    throw new ParseException('Indentation problem.', $this->offset + $nextLineNb + 1, substr($this->lines[$nextLineNb], $this->blockIndentation), $this->filename);
                }

                $this->currentLineNb = $nextLineNb - 1;

                return null;
            }

            return $this->doParseBlock($flags, $nextLineNb, $nextRawIndent, $this->blockIndentation, false, null, false, false, $context);
        } finally {
            $this->getState()->leaveNestingLevel();
        }
    }

    private function parseInlineMergeValue(int $lineNumber, string $yaml, int $flags, ?string &$context): mixed
    {
        $parser = new self();
        $parser->offset = $lineNumber + 1;
        $parser->refs = &$this->refs;
        $parser->refsBeingParsed = $this->refsBeingParsed;
        $parser->state = $this->state;
        $parser->filename = $this->filename;

        $this->getState()->enterNestingLevel($lineNumber + 2, $this->currentLine, $this->filename);

        try {
            $value = $parser->cleanup($yaml, isNested: true);
            $parser->lines = explode("\n", $value);
            $parser->numberOfParsedLines = \count($parser->lines);

            return $parser->doParseBlock($flags, 0, 0, -1, false, null, false, false, $context);
        } finally {
            $this->getState()->leaveNestingLevel();
        }
    }

    /**
     * Returns the current line number (takes the offset into account).
     *
     * @internal
     */
    public function getRealCurrentLineNb(): int
    {
        return $this->currentLineNb + $this->offset;
    }

    private function getCurrentLineIndentation(): int
    {
        return strspn($this->currentLine, ' ');
    }

    private function getLine(int $lineNumber): string
    {
        if ($lineNumber === $this->blockStartLineNb && null !== $this->firstLineOverride) {
            return $this->firstLineOverride;
        }

        $line = $this->lines[$lineNumber];

        if (0 === $this->blockIndentation) {
            return $line;
        }

        $indentation = strspn($line, ' ');

        if ($indentation >= $this->blockIndentation) {
            return substr($line, $this->blockIndentation);
        }

        if (!isset($line[$indentation])) {
            return '';
        }

        return substr($line, $indentation);
    }

    private function hasMoreLines(): bool
    {
        return $this->numberOfParsedLines - 1 > $this->currentLineNb;
    }

    private function hasOnlyEmptyRemainingLines(): bool
    {
        while ($this->moveToNextLine()) {
            if (!$this->isCurrentLineEmpty()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Moves the parser to the next line within the active block.
     */
    private function moveToNextLine(): bool
    {
        $nextLineNb = $this->currentLineNb + 1;

        if ($nextLineNb >= $this->numberOfParsedLines) {
            return false;
        }

        if ($nextLineNb === $this->blockStartLineNb && null !== $this->firstLineOverride) {
            $this->currentLineNb = $nextLineNb;
            $this->currentLine = $this->firstLineOverride;

            return true;
        }

        $rawLine = $this->lines[$nextLineNb];

        if (0 === $this->blockIndentation && !$this->blockInUnindentedCollection) {
            $this->currentLineNb = $nextLineNb;
            $this->currentLine = $rawLine;

            return true;
        }

        $indent = strspn($rawLine, ' ');
        $firstChar = $rawLine[$indent] ?? '';

        if ('' === $firstChar) {
            $this->currentLineNb = $nextLineNb;
            $this->currentLine = $indent >= $this->blockIndentation ? substr($rawLine, $this->blockIndentation) : '';

            return true;
        }

        if ('#' === $firstChar) {
            $this->currentLineNb = $nextLineNb;
            $this->currentLine = $indent >= $this->blockIndentation ? substr($rawLine, $this->blockIndentation) : substr($rawLine, $indent);

            return true;
        }

        if ($indent < $this->blockIndentation) {
            if ($indent > $this->parentIndentation && !$this->allowFirstLineDedent) {
                throw new ParseException('Indentation problem.', $this->offset + $nextLineNb + 1, substr($rawLine, max(0, $this->parentIndentation)), $this->filename);
            }

            return false;
        }

        if ($this->blockInUnindentedCollection && $indent === $this->blockIndentation && !self::isUnIndentedCollectionItemAt($rawLine, $indent)) {
            return false;
        }

        $this->allowFirstLineDedent = false;
        $this->currentLineNb = $nextLineNb;
        $this->currentLine = substr($rawLine, $this->blockIndentation);

        return true;
    }

    /**
     * Moves the parser to the previous line.
     */
    private function moveToPreviousLine(): bool
    {
        if ($this->currentLineNb <= $this->blockStartLineNb) {
            return false;
        }

        $this->currentLine = $this->getLine(--$this->currentLineNb);

        return true;
    }

    /**
     * Parses a YAML value.
     *
     * @param string $value   A YAML value
     * @param int    $flags   A bit field of Yaml::PARSE_* constants to customize the YAML parser behavior
     * @param string $context The parser context (either sequence or mapping)
     *
     * @throws ParseException When reference does not exist
     */
    private function parseValue(string $value, int $flags, string $context): mixed
    {
        if (str_starts_with($value, '*')) {
            $value = substr($value, 1);

            // remove comments
            if (self::preg_match('/(?<![ \t])[ \t]++#/', $value, $match, \PREG_OFFSET_CAPTURE)) {
                $value = substr($value, 0, $match[0][1]);
            }

            if (!\array_key_exists($value, $this->refs)) {
                if (false !== $pos = array_search($value, $this->refsBeingParsed, true)) {
                    throw new ParseException(\sprintf('Circular reference [%s] detected for reference "%s".', implode(', ', array_merge(\array_slice($this->refsBeingParsed, $pos), [$value])), $value), $this->currentLineNb + 1, $this->currentLine, $this->filename);
                }

                throw new ParseException(\sprintf('Reference "%s" does not exist.', $value), $this->currentLineNb + 1, $this->currentLine, $this->filename);
            }

            $this->getState()->countAlias($value, $this->refs[$value], $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);

            return $this->refs[$value];
        }

        if (\in_array($value[0], ['!', '|', '>'], true) && self::preg_match('/^(?:'.self::TAG_PATTERN.'[ \t]+)?'.self::BLOCK_SCALAR_HEADER_PATTERN.'$/', $value, $matches)) {
            $this->checkBlockScalarTag($matches['tag'], $flags);
            $modifiers = $matches['modifiers'] ?? '';

            return $this->resolveBlockScalarTag($matches['tag'], $this->parseBlockScalar($matches['separator'], preg_replace('#\d+#', '', $modifiers), abs((int) $modifiers)));
        }

        try {
            if ('' !== $value && '{' === $value[0]) {
                $cursor = \strlen(rtrim($this->currentLine)) - \strlen(rtrim($value));

                return Inline::parse($this->lexInlineMapping($cursor), $flags, $this->refs, $this->state);
            } elseif ('' !== $value && '[' === $value[0]) {
                $cursor = \strlen(rtrim($this->currentLine)) - \strlen(rtrim($value));

                return Inline::parse($this->lexInlineSequence($cursor), $flags, $this->refs, $this->state);
            }

            switch ($value[0] ?? '') {
                case '"':
                case "'":
                    $cursor = \strlen(rtrim($this->currentLine)) - \strlen(rtrim($value));
                    $parsedValue = Inline::parse($this->lexInlineQuotedString($cursor), $flags, $this->refs, $this->state);

                    if (isset($this->currentLine[$cursor]) && preg_replace('/\s*(#.*)?$/A', '', substr($this->currentLine, $cursor))) {
                        throw new ParseException(\sprintf('Unexpected characters near "%s".', substr($this->currentLine, $cursor)));
                    }

                    return $parsedValue;
                default:
                    $lines = [];

                    while ($this->moveToNextLine()) {
                        // unquoted strings end before the first unindented line
                        if (0 === $this->getCurrentLineIndentation()) {
                            $this->moveToPreviousLine();

                            break;
                        }

                        if ($this->isCurrentLineComment()) {
                            break;
                        }

                        if ('mapping' === $context && str_contains($this->currentLine, ': ')) {
                            throw new ParseException('A colon cannot be used in an unquoted mapping value.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
                        }

                        $lines[] = trim($this->currentLine);
                    }

                    for ($i = 0, $linesCount = \count($lines), $previousLineBlank = false; $i < $linesCount; ++$i) {
                        if ('' === $lines[$i]) {
                            $value .= "\n";
                            $previousLineBlank = true;
                        } elseif ($previousLineBlank) {
                            $value .= $lines[$i];
                            $previousLineBlank = false;
                        } else {
                            $value .= ' '.$lines[$i];
                            $previousLineBlank = false;
                        }
                    }

                    Inline::$parsedLineNumber = $this->getRealCurrentLineNb();

                    $parsedValue = Inline::parse($value, $flags, $this->refs, $this->state);

                    if ('mapping' === $context && \is_string($parsedValue) && '"' !== $value[0] && "'" !== $value[0] && '[' !== $value[0] && '{' !== $value[0] && '!' !== $value[0] && str_contains($parsedValue, ': ')) {
                        throw new ParseException('A colon cannot be used in an unquoted mapping value.', $this->getRealCurrentLineNb() + 1, $value, $this->filename);
                    }

                    return $parsedValue;
            }
        } catch (ParseException $e) {
            if (!str_starts_with($e->getMessage(), 'Indentation problem')) {
                $e->setParsedLine($this->getRealCurrentLineNb() + 1);
                $e->setSnippet($this->currentLine);
            }

            throw $e;
        }
    }

    /**
     * Parses a block scalar.
     *
     * @param string $style       The style indicator that was used to begin this block scalar (| or >)
     * @param string $chomping    The chomping indicator that was used to begin this block scalar (+ or -)
     * @param int    $indentation The indentation indicator that was used to begin this block scalar
     */
    private function parseBlockScalar(string $style, string $chomping = '', int $indentation = 0): string
    {
        $notEOF = $this->moveToNextLine();
        if (!$notEOF) {
            return '';
        }

        $isCurrentLineBlank = $this->isCurrentLineBlank();
        $blockLines = [];

        // leading blank lines are consumed before determining indentation
        while ($notEOF && $isCurrentLineBlank && (!$indentation || \strlen($this->currentLine) <= $indentation)) {
            // newline only if not EOF
            if ($notEOF = $this->moveToNextLine()) {
                $blockLines[] = '';
                $isCurrentLineBlank = $this->isCurrentLineBlank();
            }
        }

        // determine indentation if not specified
        if (0 === $indentation) {
            $currentLineLength = \strlen($this->currentLine);

            for ($i = 0; $i < $currentLineLength && ' ' === $this->currentLine[$i]; ++$i) {
                ++$indentation;
            }
        }

        if (!$notEOF || !$indentation || strspn($this->currentLine, ' ') < $indentation) {
            if ($notEOF) {
                $this->moveToPreviousLine();
            } elseif (!$this->isCurrentLineLastLineInDocument()) {
                $blockLines[] = '';
            }

            // without content, the line breaks of the empty lines are only kept with the "+" chomping indicator
            return '+' === $chomping ? str_repeat("\n", \count($blockLines)) : '';
        }

        $pattern = \sprintf('/^ {%d}(.*)$/', $indentation);

        while (
            $notEOF && (
                $isCurrentLineBlank
                || self::preg_match($pattern, $this->currentLine, $matches)
            )
        ) {
            if ($isCurrentLineBlank && \strlen($this->currentLine) > $indentation) {
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

        if ($notEOF) {
            $blockLines[] = '';
            $this->moveToPreviousLine();
        } elseif (!$this->isCurrentLineLastLineInDocument()) {
            $blockLines[] = '';
        }

        // folded style
        if ('>' === $style) {
            $text = '';
            $previousLineIndented = null;
            $previousLineBlank = false;

            for ($i = 0, $blockLinesCount = \count($blockLines); $i < $blockLinesCount; ++$i) {
                if ('' === $blockLines[$i]) {
                    $text .= "\n";
                    $previousLineBlank = true;
                } elseif (' ' === $blockLines[$i][0] || "\t" === $blockLines[$i][0]) {
                    $text .= (null === $previousLineIndented ? '' : "\n").$blockLines[$i];
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
        if ('' === $chomping && str_ends_with($text, "\n")) {
            $text = rtrim($text, "\n")."\n";
        } elseif ('-' === $chomping) {
            $text = rtrim($text, "\n");
        }

        return $text;
    }

    /**
     * Parses a block scalar, optionally tagged, that is the root node of the document.
     */
    private function parseRootBlockScalar(int $flags): mixed
    {
        Inline::initialize($flags, $this->getRealCurrentLineNb(), $this->filename);
        $data = $this->parseValue(rtrim($this->currentLine), $flags, '');

        while ($this->moveToNextLine()) {
            if (!$this->isCurrentLineEmpty()) {
                throw new ParseException('Unable to parse.', $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
            }
        }

        return $data;
    }

    private function checkBlockScalarTag(string $tag, int $flags): void
    {
        if ('' !== $tag && '!' !== $tag[1] && !(Yaml::PARSE_CUSTOM_TAGS & $flags)) {
            throw new ParseException(\sprintf('Tags support is not enabled. Enable the "Yaml::PARSE_CUSTOM_TAGS" flag to use "%s".', $tag), $this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);
        }
    }

    private function resolveBlockScalarTag(string $tag, string $data): mixed
    {
        if ('' !== $tag && '!' !== $tag) {
            return '!' === $tag[1] ? Inline::evaluateTaggedScalar($tag, $data) : new TaggedValue(substr($tag, 1), $data);
        }

        return $data;
    }

    /**
     * Returns true if the next line is indented.
     */
    private function isNextLineIndented(): bool
    {
        return null !== ($indent = $this->getNextContentLineIndentation()) && $indent > $this->getCurrentLineIndentation();
    }

    /**
     * Returns the indentation of the next line that is not empty or a comment, or null if there is none.
     *
     * @param-out string|null $line
     * @param-out int|null    $lineNb
     */
    private function getNextContentLineIndentation(?string &$line = null, ?int &$lineNb = null): ?int
    {
        for ($i = $this->currentLineNb + 1; $i < $this->numberOfParsedLines; ++$i) {
            $rawLine = $this->lines[$i];
            $indent = strspn($rawLine, ' ');
            $firstChar = $rawLine[$indent] ?? '';

            if ('' === $firstChar || '#' === $firstChar) {
                continue;
            }

            if (0 !== $this->blockIndentation || $this->blockInUnindentedCollection) {
                if ($indent < $this->blockIndentation) {
                    if ($indent > $this->parentIndentation && !$this->allowFirstLineDedent) {
                        throw new ParseException('Indentation problem.', $this->offset + $i + 1, substr($rawLine, max(0, $this->parentIndentation)), $this->filename);
                    }

                    $line = null;
                    $lineNb = null;

                    return null;
                }

                if ($this->blockInUnindentedCollection && $indent === $this->blockIndentation && !self::isUnIndentedCollectionItemAt($rawLine, $indent)) {
                    $line = null;
                    $lineNb = null;

                    return null;
                }
            }

            $line = substr($rawLine, $this->blockIndentation);
            $lineNb = $i;

            return $indent - $this->blockIndentation;
        }

        $line = null;
        $lineNb = null;

        return null;
    }

    private function isCurrentLineEmpty(): bool
    {
        $firstChar = $this->currentLine[strspn($this->currentLine, ' ')] ?? '';

        return '' === $firstChar || '#' === $firstChar;
    }

    private function isCurrentLineBlank(): bool
    {
        return strspn($this->currentLine, ' ') === \strlen($this->currentLine);
    }

    private function isCurrentLineComment(): bool
    {
        return '#' === ($this->currentLine[strspn($this->currentLine, ' ')] ?? '');
    }

    private function isCurrentLineLastLineInDocument(): bool
    {
        return $this->currentLineNb >= $this->numberOfParsedLines - 1;
    }

    private function cleanup(string $value, ?bool &$hasContentOnStartMarkerLine = null, bool $isNested = false): string
    {
        $hasContentOnStartMarkerLine = false;

        $value = str_replace(["\r\n", "\r"], "\n", $value);

        // remove the byte order mark (BOM) at the start of the stream, not at the start of a nested block
        if (!$isNested && str_starts_with($value, "\u{FEFF}")) {
            $value = substr($value, 3);
        }

        // remove the YAML header, and the comments and empty lines before the content
        $value = $this->removeLeadingLines('#^(?:[ \t]*+(?:\#[^\n]*+)?+\n)*+(?:%YAML[: ][\d.]++[^\n]*+\n(?:[ \t]*+(?:\#[^\n]*+)?+\n)*+)?+#', $value);

        // document markers and the properties of the root node are only found at the top level, not in nested blocks
        if ($isNested) {
            return $value;
        }

        // remove start of the document marker (---), with the YAML 1.0 directives or the node properties that can follow it alone, as they were always ignored
        $value = preg_replace('#^---(?:[ \t]++(?:%[^\n]*+|(?:[!&][^ \t\n]*+[ \t]*+)++(?=(?:\#[^\n]*+)?+(?:\n|\z)))?+|(?=\n)|\z)#', '', $value, -1, $count);

        // remove the anchor of the root node, as nothing can refer to it
        $value = preg_replace('#^((?:![^! \t\n][^ \t\n]*+[ \t]++)?+)&[^ \t\n]++(?:[ \t]++|(?=\n)|\z)#', '$1', $value);

        // remove the lines left without content
        $offset = $this->offset;
        $value = $this->removeLeadingLines('#^(?:[ \t]*+(?:\#[^\n]*+)?+\n)++#', $value);
        $hasContentOnStartMarkerLine = 1 === $count && $offset === $this->offset;

        // remove end of the document marker (...), which can only be followed by comments
        $value = preg_replace('#^\.\.\.(?:[ \t]++(?:\#[^\n]*+)?+)?+(?:\n[ \t]*+(?:\#[^\n]*+)?+)*+\z#m', '', $value);

        if (self::preg_match('#^(?:---|\.\.\.)(?:[ \t][^\n]*+)?+$#m', $value, $matches, \PREG_OFFSET_CAPTURE)) {
            throw new ParseException('Multiple documents are not supported.', $this->offset + substr_count($value, "\n", 0, $matches[0][1]) + 1, $matches[0][0], $this->filename);
        }

        return $value;
    }

    /**
     * Turns "!tag &anchor value" into "&anchor !tag value", as both orders are allowed.
     *
     * Built-in tags are left alone: "!!str &foo" is the "&foo" string, as in flow collections.
     */
    private function moveAnchorBeforeTag(string $value): string
    {
        if (str_starts_with($value, '!!')) {
            return $value;
        }

        return preg_replace('#^'.self::TAG_PATTERN.' ++(&[^ ]++)#', '$2 $1', $value);
    }

    private function removeLeadingLines(string $pattern, string $value): string
    {
        $trimmedValue = preg_replace($pattern, '', $value);

        // items have been removed, update the offset
        $this->offset += substr_count($value, "\n") - substr_count($trimmedValue, "\n");

        return $trimmedValue;
    }

    private function isNextLineUnIndentedCollection(): bool
    {
        return $this->getCurrentLineIndentation() === $this->getNextContentLineIndentation($line)
            && self::isUnIndentedCollectionItem($line);
    }

    private static function isUnIndentedCollectionItem(string $line): bool
    {
        return '-' === rtrim($line) || str_starts_with($line, '- ');
    }

    private static function isUnIndentedCollectionItemAt(string $line, int $indent): bool
    {
        if ('-' !== ($line[$indent] ?? '')) {
            return false;
        }

        if (!isset($line[$indent + 1]) || ' ' === $line[$indent + 1]) {
            return true;
        }

        return '' === rtrim(substr($line, $indent + 1));
    }

    /**
     * A local wrapper for "preg_match" which will throw a ParseException if there
     * is an internal error in the PCRE engine.
     *
     * This avoids us needing to check for "false" every time PCRE is used
     * in the YAML engine
     *
     * @throws ParseException on a PCRE internal error
     *
     * @internal
     */
    public static function preg_match(string $pattern, string $subject, ?array &$matches = null, int $flags = 0, int $offset = 0): int
    {
        if (false === $ret = preg_match($pattern, $subject, $matches, $flags, $offset)) {
            throw new ParseException(preg_last_error_msg());
        }

        return $ret;
    }

    /**
     * Trim the tag on top of the value.
     *
     * Prevent values such as "!foo {quz: bar}" to be considered as
     * a mapping block.
     */
    private function trimTag(string $value): string
    {
        if ('!' === $value[0]) {
            return ltrim(substr($value, 1, strcspn($value, " \r\n", 1)), ' ');
        }

        return $value;
    }

    private function getLineTag(string $value, int $flags, bool $nextLineCheck = true): ?string
    {
        if ('' === $value || '!' !== $value[0] || 1 !== self::preg_match('/^'.self::TAG_PATTERN.'(?: ++(?:#.*+)?+)?+$/', $value, $matches)) {
            return null;
        }

        // unlike a sequence item, the value of a mapping key can also be a sequence that is not indented
        if ($nextLineCheck && !$this->isNextLineIndented() && (self::isUnIndentedCollectionItem($this->currentLine) || !$this->isNextLineUnIndentedCollection())) {
            return null;
        }

        $tag = substr($matches['tag'], 1);

        // Built-in tags
        if ($tag && '!' === $tag[0]) {
            // "!!str" and "!!binary" can be resolved as inline scalars when they don't apply to a nested block
            if (!$nextLineCheck && \in_array($tag, ['!str', '!binary'], true) && !$this->isNextLineIndented()) {
                return null;
            }

            throw new ParseException(\sprintf('The built-in tag "!%s" is not implemented.', $tag), $this->getRealCurrentLineNb() + 1, $value, $this->filename);
        }

        if (Yaml::PARSE_CUSTOM_TAGS & $flags) {
            return $tag;
        }

        throw new ParseException(\sprintf('Tags support is not enabled. You must use the flag "Yaml::PARSE_CUSTOM_TAGS" to use "%s".', $matches['tag']), $this->getRealCurrentLineNb() + 1, $value, $this->filename);
    }

    private function lexInlineQuotedString(int &$cursor = 0): string
    {
        $quotation = $this->currentLine[$cursor];
        $value = $quotation;
        ++$cursor;

        $previousLineWasNewline = true;
        $previousLineWasTerminatedWithBackslash = false;
        $lineNumber = 0;

        do {
            if (++$lineNumber > 1) {
                $cursor += strspn($this->currentLine, " \t", $cursor);
            }

            if ($isBlankLine = '' === trim($this->currentLine, " \t")) {
                $value .= "\n";
            } elseif (!$previousLineWasNewline && !$previousLineWasTerminatedWithBackslash) {
                $value .= ' ';
            }

            $line = '';
            $escapedLength = 0;

            for (; \strlen($this->currentLine) > $cursor; ++$cursor) {
                switch ($this->currentLine[$cursor]) {
                    case '\\':
                        if ("'" === $quotation) {
                            $line .= '\\';
                        } elseif (isset($this->currentLine[++$cursor])) {
                            $line .= '\\'.$this->currentLine[$cursor];
                            $escapedLength = \strlen($line);
                        }

                        break;
                    case $quotation:
                        ++$cursor;

                        if ("'" === $quotation && isset($this->currentLine[$cursor]) && "'" === $this->currentLine[$cursor]) {
                            $line .= "''";
                            break;
                        }

                        return $value.$line.$quotation;
                    default:
                        $line .= $this->currentLine[$cursor];
                }
            }

            if ($isBlankLine) {
                $previousLineWasNewline = true;
                $previousLineWasTerminatedWithBackslash = false;
            } elseif ('"' === $quotation && 1 === (\strlen($this->currentLine) - \strlen(rtrim($this->currentLine, '\\'))) % 2) {
                $previousLineWasNewline = false;
                $previousLineWasTerminatedWithBackslash = true;
            } else {
                $previousLineWasNewline = false;
                $previousLineWasTerminatedWithBackslash = false;

                // trailing white space is removed when the line is folded, unless it is escaped
                $line = substr($line, 0, $escapedLength).rtrim(substr($line, $escapedLength), " \t");
            }

            $value .= $line;

            if ($this->hasMoreLines()) {
                $cursor = 0;
            }
        } while ($this->moveToNextLine());

        throw new ParseException('Unterminated quoted string. If it spans multiple lines, its continuation lines must be indented at least as deeply as where it starts.');
    }

    private function lexUnquotedString(int &$cursor): string
    {
        $offset = $cursor;

        while ($cursor < \strlen($this->currentLine)) {
            if (\in_array($this->currentLine[$cursor], ['[', ']', '{', '}', ',', ':'], true)) {
                break;
            }

            if (\in_array($this->currentLine[$cursor], [' ', "\t"], true) && '#' === ($this->currentLine[$cursor + 1] ?? '')) {
                break;
            }

            ++$cursor;
        }

        if ($cursor === $offset) {
            throw new ParseException('Malformed unquoted YAML string.');
        }

        return substr($this->currentLine, $offset, $cursor - $offset);
    }

    private function lexInlineAnchorOrAlias(int &$cursor): string
    {
        $offset = $cursor;
        ++$cursor;

        while ($cursor < \strlen($this->currentLine) && !\in_array($this->currentLine[$cursor], [' ', "\t", ',', '[', ']', '{', '}'], true)) {
            ++$cursor;
        }

        return substr($this->currentLine, $offset, $cursor - $offset);
    }

    private function lexInlineMapping(int &$cursor = 0, bool $consumeUntilEol = true): string
    {
        return $this->lexInlineStructure($cursor, '}', $consumeUntilEol);
    }

    private function lexInlineSequence(int &$cursor = 0, bool $consumeUntilEol = true): string
    {
        return $this->lexInlineStructure($cursor, ']', $consumeUntilEol);
    }

    private function lexInlineStructure(int &$cursor, string $closingTag, bool $consumeUntilEol = true): string
    {
        $state = $this->getState();
        $state->enterNestingLevel($this->getRealCurrentLineNb() + 1, $this->currentLine, $this->filename);

        try {
            return $this->doLexInlineStructure($cursor, $closingTag, $consumeUntilEol);
        } finally {
            $state->leaveNestingLevel();
        }
    }

    private function doLexInlineStructure(int &$cursor, string $closingTag, bool $consumeUntilEol = true): string
    {
        $value = $this->currentLine[$cursor];
        ++$cursor;

        do {
            $this->consumeWhitespaces($cursor);

            while (isset($this->currentLine[$cursor])) {
                switch ($this->currentLine[$cursor]) {
                    case '"':
                    case "'":
                        $value .= $this->lexInlineQuotedString($cursor);
                        break;
                    case ':':
                    case ',':
                        $value .= $this->currentLine[$cursor];
                        ++$cursor;
                        break;
                    case '{':
                        $value .= $this->lexInlineMapping($cursor, false);
                        break;
                    case '[':
                        $value .= $this->lexInlineSequence($cursor, false);
                        break;
                    case '&':
                    case '*':
                        $value .= $this->lexInlineAnchorOrAlias($cursor);
                        break;
                    case $closingTag:
                        $value .= $this->currentLine[$cursor];
                        ++$cursor;

                        if ($consumeUntilEol && isset($this->currentLine[$cursor]) && ($whitespaces = strspn($this->currentLine, ' ', $cursor) + $cursor) < \strlen($this->currentLine) && '#' !== $this->currentLine[$whitespaces]) {
                            throw new ParseException(\sprintf('Unexpected token "%s".', trim(substr($this->currentLine, $cursor))));
                        }

                        return $value;
                    case '#':
                        break 2;
                    default:
                        $value .= $this->lexUnquotedString($cursor);
                }

                if ($this->consumeWhitespaces($cursor)) {
                    $value .= ' ';
                }
            }

            if ($this->hasMoreLines()) {
                $cursor = 0;
            }
        } while ($this->moveToNextLine());

        throw new ParseException('Malformed inline YAML string.');
    }

    private function consumeWhitespaces(int &$cursor): bool
    {
        $whitespacesConsumed = 0;

        do {
            $whitespaceOnlyTokenLength = strspn($this->currentLine, " \t", $cursor);
            $whitespacesConsumed += $whitespaceOnlyTokenLength;
            $cursor += $whitespaceOnlyTokenLength;

            if (isset($this->currentLine[$cursor])) {
                return 0 < $whitespacesConsumed;
            }

            if ($this->hasMoreLines()) {
                $cursor = 0;
            }
        } while ($this->moveToNextLine());

        return 0 < $whitespacesConsumed;
    }
}
