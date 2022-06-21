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
     *
     * @var int
     */
    protected $indentation;

    /**
     * String of spaces with length equal to $this->indentation.
     *
     * @var string
     */
    private $indentStr;

    public function __construct(int $indentation = 4)
    {
        if ($indentation < 1) {
            throw new \InvalidArgumentException('The indentation must be greater than zero.');
        }

        $this->indentation = $indentation;
        $this->indentStr = str_repeat(' ', $indentation);
    }

    /**
     * Dumps a PHP value to YAML.
     *
     * @param mixed $input  The PHP value
     * @param int   $inline The level where you switch to inline YAML
     * @param int   $indent The level of indentation (used internally)
     * @param int   $flags  A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
     *
     * @return string The YAML representation of the PHP value
     */
    public function dump($input, int $inline = 0, int $indent = 0, int $flags = 0): string
    {
        $prefix = $indent ? str_repeat(' ', $indent) : '';

        if ($this->shouldDumpAsInline($inline, $input, $flags)) {
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

            $tagged = false;
            if ($value instanceof TaggedValue) {
                $output .= ' !'.$value->getTag();
                $value = $value->getValue();
                if ($value instanceof TaggedValue) {
                    throw new \InvalidArgumentException('Nested tags are not supported.');
                }
                $tagged = true;
            }

            if (Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK & $flags && \is_string($value) && false !== strpos($value, "\n") && false === strpos($value, "\r")) {
                $output .= ' |';
                // If the first line starts with a space character, the spec requires a blockIndicationIndicator
                // http://www.yaml.org/spec/1.2/spec.html#id2793979
                if (' ' === substr($value, 0, 1)) {
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
                        $output .= $prefix.$this->indentStr.$row;
                    }
                }

                continue;
            }

            $willBeInlined = $this->shouldDumpAsInline($inline - 1, $value, $flags);

            $output .= ($willBeInlined ? ' ' : "\n")
                .$this->dump(
                    $value,
                    $inline - 1,
                    $willBeInlined
                        ? 0
                        : $indent + ($tagged && !$dumpAsMap ? 2 : $this->indentation),
                    $flags)
                .($willBeInlined ? "\n" : '')
            ;
        }

        return $output;
    }

    private function shouldDumpAsInline(int $inline, $value, int $flags): bool
    {
        if ($inline <= 0 || empty($value)) {
            return true;
        }

        $dumpObjectAsInlineMap = true;

        if (Yaml::DUMP_OBJECT_AS_MAP & $flags && ($value instanceof \ArrayObject || $value instanceof \stdClass)) {
            $dumpObjectAsInlineMap = empty((array) $value);
        }

        return !\is_array($value) && !$value instanceof TaggedValue && $dumpObjectAsInlineMap;
    }
}
