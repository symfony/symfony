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

/**
 * Dumper dumps PHP variables to YAML strings.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Dumper
{
    /**
     * The amount of spaces to use for indentation of nested nodes.
     *
     * @var int
     */
    protected $indentation = 4;

    /**
     * Sets the indentation.
     *
     * @param int $num The amount of spaces to use for indentation of nested nodes.
     */
    public function setIndentation($num)
    {
        if ($num < 1) {
            throw new \InvalidArgumentException('The indentation must be greater than zero.');
        }

        $this->indentation = (int) $num;
    }

    /**
     * Dumps a PHP value to YAML.
     *
     * @param mixed $input                  The PHP value
     * @param int   $inline                 The level where you switch to inline YAML
     * @param int   $indent                 The level of indentation (used internally)
     * @param bool  $exceptionOnInvalidType true if an exception must be thrown on invalid types (a PHP resource or object), false otherwise
     * @param bool  $objectSupport          true if object support is enabled, false otherwise
     *
     * @return string The YAML representation of the PHP value
     */
    public function dump($input, $inline = 0, $indent = 0, $exceptionOnInvalidType = false, $objectSupport = false)
    {
        $output = '';
        $prefix = $indent ? str_repeat(' ', $indent) : '';

        if ($inline <= 0 || !is_array($input) || empty($input)) {
            $output .= $prefix.Inline::dump($input, $exceptionOnInvalidType, $objectSupport);
        } else {
            $isAHash = Inline::isHash($input);

            foreach ($input as $key => $value) {
                $willBeInlined = $inline - 1 <= 0 || !is_array($value) || empty($value);

                $output .= sprintf('%s%s%s%s',
                    $prefix,
                    $isAHash ? Inline::dump($key, $exceptionOnInvalidType, $objectSupport).':' : '-',
                    $willBeInlined ? ' ' : "\n",
                    $this->dump($value, $inline - 1, $willBeInlined ? 0 : $indent + $this->indentation, $exceptionOnInvalidType, $objectSupport)
                ).($willBeInlined ? "\n" : '');
            }
        }

        return $output;
    }
}
