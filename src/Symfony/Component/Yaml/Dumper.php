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

use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Dumper dumps PHP variables to YAML strings.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Dumper
{
    /**
     * @param int $indentation The amount of spaces to use for indentation of nested nodes
     */
    public function __construct(private int $indentation = 4)
    {
        if ($indentation < 1) {
            throw new \InvalidArgumentException('The indentation must be greater than zero.');
        }
    }

    /**
     * Dumps a PHP value to YAML.
     *
     * @param mixed                     $input  The PHP value
     * @param int                       $inline The level where you switch to inline YAML
     * @param int                       $indent The level of indentation (used internally)
     * @param int-mask-of<Yaml::DUMP_*> $flags  A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
     */
    public function dump(mixed $input, int $inline = 0, int $indent = 0, int $flags = 0): string
    {
        $output = '';
        $prefix = $indent ? str_repeat(' ', $indent) : '';
        $dumpObjectAsInlineMap = true;

        if (Yaml::DUMP_OBJECT_AS_MAP & $flags && ($input instanceof \ArrayObject || $input instanceof \stdClass)) {
            $dumpObjectAsInlineMap = !(array) $input;
        }

        if ($inline <= 0 || (!\is_array($input) && !$input instanceof TaggedValue && $dumpObjectAsInlineMap) || !$input) {
            $output .= $prefix.Inline::dump($input, $flags);
        } elseif ($input instanceof TaggedValue) {
            $output .= $this->dumpTaggedValue($input, $inline, $indent, $flags, $prefix);
        } else {
            $dumpAsMap = Inline::isHash($input);

            foreach ($input as $key => $value) {
                if ('' !== $output && "\n" !== $output[-1]) {
                    $output .= "\n";
                }

                if (\is_int($key) && Yaml::DUMP_NUMERIC_KEY_AS_STRING & $flags) {
                    $key = (string) $key;
                }

                if (Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK & $flags && \is_string($value) && str_contains($value, "\n") && !str_contains($value, "\r")) {
                    $blockIndentationIndicator = $this->getBlockIndentationIndicator($value);

                    if (isset($value[-2]) && "\n" === $value[-2] && "\n" === $value[-1]) {
                        $blockChompingIndicator = '+';
                    } elseif ("\n" === $value[-1]) {
                        $blockChompingIndicator = '';
                    } else {
                        $blockChompingIndicator = '-';
                    }

                    $output .= \sprintf('%s%s%s |%s%s', $prefix, $dumpAsMap ? Inline::dump($key, $flags).':' : '-', '', $blockIndentationIndicator, $blockChompingIndicator);

                    foreach (explode("\n", $value) as $row) {
                        if ('' === $row) {
                            $output .= "\n";
                        } else {
                            $output .= \sprintf("\n%s%s%s", $prefix, str_repeat(' ', $this->indentation), $row);
                        }
                    }

                    continue;
                }

                if ($value instanceof TaggedValue) {
                    $output .= \sprintf('%s%s !%s', $prefix, $dumpAsMap ? Inline::dump($key, $flags).':' : '-', $value->getTag());

                    if (Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK & $flags && \is_string($value->getValue()) && str_contains($value->getValue(), "\n") && !str_contains($value->getValue(), "\r\n")) {
                        $blockIndentationIndicator = $this->getBlockIndentationIndicator($value->getValue());
                        $output .= \sprintf(' |%s', $blockIndentationIndicator);

                        foreach (explode("\n", $value->getValue()) as $row) {
                            $output .= \sprintf("\n%s%s%s", $prefix, str_repeat(' ', $this->indentation), $row);
                        }

                        continue;
                    }

                    if ($inline - 1 <= 0 || null === $value->getValue() || \is_scalar($value->getValue())) {
                        $output .= ' '.$this->dump($value->getValue(), $inline - 1, 0, $flags)."\n";
                    } else {
                        $output .= "\n";
                        $output .= $this->dump($value->getValue(), $inline - 1, $dumpAsMap ? $indent + $this->indentation : $indent + 2, $flags);
                    }

                    continue;
                }

                $dumpObjectAsInlineMap = true;

                if (Yaml::DUMP_OBJECT_AS_MAP & $flags && ($value instanceof \ArrayObject || $value instanceof \stdClass)) {
                    $dumpObjectAsInlineMap = !(array) $value;
                }

                $willBeInlined = $inline - 1 <= 0 || !\is_array($value) && $dumpObjectAsInlineMap || !$value;

                $output .= \sprintf('%s%s%s%s',
                    $prefix,
                    $dumpAsMap ? Inline::dump($key, $flags).':' : '-',
                    $willBeInlined ? ' ' : "\n",
                    $this->dump($value, $inline - 1, $willBeInlined ? 0 : $indent + $this->indentation, $flags)
                ).($willBeInlined ? "\n" : '');
            }
        }

        return $output;
    }

    private function dumpTaggedValue(TaggedValue $value, int $inline, int $indent, int $flags, string $prefix): string
    {
        $output = \sprintf('%s!%s', $prefix ? $prefix.' ' : '', $value->getTag());

        if (Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK & $flags && \is_string($value->getValue()) && str_contains($value->getValue(), "\n") && !str_contains($value->getValue(), "\r\n")) {
            $blockIndentationIndicator = $this->getBlockIndentationIndicator($value->getValue());
            $output .= \sprintf(' |%s', $blockIndentationIndicator);

            foreach (explode("\n", $value->getValue()) as $row) {
                $output .= \sprintf("\n%s%s%s", $prefix, str_repeat(' ', $this->indentation), $row);
            }

            return $output;
        }

        if ($inline - 1 <= 0 || null === $value->getValue() || \is_scalar($value->getValue())) {
            return $output.' '.$this->dump($value->getValue(), $inline - 1, 0, $flags)."\n";
        }

        return $output."\n".$this->dump($value->getValue(), $inline - 1, $indent, $flags);
    }

    private function getBlockIndentationIndicator(string $value): string
    {
        $lines = explode("\n", $value);

        // If the first line (that is neither empty nor contains only spaces)
        // starts with a space character, the spec requires a block indentation indicator
        // http://www.yaml.org/spec/1.2/spec.html#id2793979
        foreach ($lines as $line) {
            if ('' !== trim($line, ' ')) {
                return str_starts_with($line, ' ') ? (string) $this->indentation : '';
            }
        }

        return '';
    }
}
