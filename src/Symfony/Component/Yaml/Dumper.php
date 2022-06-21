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
     * The amount of spaces to use for indentation of nested nodes.
     */
    private int $indentation;

    /**
     * String of spaces with length equal to $this->indentation.
     */
    private string $indentStr;

    public function __construct(int $indentation = 4)
    {
        if ($indentation < 1) {
            throw new \InvalidArgumentException('The indentation must be greater than zero.');
        }

        $this->indentation = $indentation;
        $this->indentStr = \str_repeat(' ', $indentation);
    }

    /**
     * Dumps a PHP value to YAML.
     *
     * @param mixed $input  The PHP value
     * @param int   $inline The level where you switch to inline YAML
     * @param int   $indent The level of indentation (used internally)
     * @param int   $flags  A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
     */
    public function dump(mixed $input, int $inline = 0, int $indent = 0, int $flags = 0): string
    {
        $prefix = $indent ? str_repeat(' ', $indent) : '';
        $dumpObjectAsInlineMap = true;

        if (Yaml::DUMP_OBJECT_AS_MAP & $flags && ($input instanceof \ArrayObject || $input instanceof \stdClass)) {
            $dumpObjectAsInlineMap = empty((array) $input);
        }

        if ($inline <= 0 || (!\is_array($input) && !$input instanceof TaggedValue && $dumpObjectAsInlineMap) || empty($input)) {
            return $prefix.Inline::dump($input, $flags);
        }

        $dumpAsMap = Inline::isHash($input);

        $output = '';
        foreach ($input as $key => $value) {
            if ('' !== $output && "\n" !== $output[-1]) {
                $output .= "\n";
            }
            $output .= $prefix;
            $output .= $dumpAsMap ? Inline::dump($key, $flags).':' : '-';

            if (Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK & $flags && \is_string($value) && str_contains($value, "\n") && !str_contains($value, "\r")) {
                $output .= ' |';
                // If the first line starts with a space character, the spec requires a blockIndicationIndicator
                // http://www.yaml.org/spec/1.2/spec.html#id2793979
                if (str_starts_with($value, ' ')) {
                    $output .= $this->indentation;
                }

                if (isset($value[-2]) && "\n" === $value[-2] && "\n" === $value[-1]) {
                    $output .= '+';
                } elseif ("\n" !== $value[-1]) {
                    $output .= '-';
                }

                foreach (explode("\n", $value) as $row) {
                    $output .= "\n";
                    if ('' !== $row) {
                        $output .= $prefix . $this->indentStr . $row;
                    }
                }

                continue;
            }

            if ($value instanceof TaggedValue) {
                $output .= ' !' . $value->getTag();

                if (Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK & $flags && \is_string($value->getValue()) && str_contains($value->getValue(), "\n") && !str_contains($value->getValue(), "\r\n")) {
                    // If the first line starts with a space character, the spec requires a blockIndicationIndicator
                    // http://www.yaml.org/spec/1.2/spec.html#id2793979
                    $blockIndentationIndicator = str_starts_with($value->getValue(), ' ') ? (string) $this->indentation : '';
                    $output .= sprintf(' |%s', $blockIndentationIndicator);

                    foreach (explode("\n", $value->getValue()) as $row) {
                        $output .= "\n"
                            . $prefix
                            . str_repeat(' ', $this->indentation)
                            . $row
                        ;
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
                $dumpObjectAsInlineMap = empty((array) $value);
            }

            $willBeInlined = $inline - 1 <= 0 || !\is_array($value) && $dumpObjectAsInlineMap || empty($value);

            $output .= ($willBeInlined ? ' ' : "\n")
                . $this->dump(
                    $value,
                    $inline - 1,
                    $willBeInlined ? 0 : $indent + $this->indentation,
                    $flags,
                )
                . ($willBeInlined ? "\n" : '')
            ;
        }

        return $output;
    }
}
