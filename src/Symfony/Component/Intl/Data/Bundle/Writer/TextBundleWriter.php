<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Bundle\Writer;

use Symfony\Component\Intl\Exception\UnexpectedTypeException;

/**
 * Writes .txt resource bundles.
 *
 * The resulting files can be converted to binary .res files using a
 * {@link \Symfony\Component\Intl\ResourceBundle\Compiler\BundleCompilerInterface}
 * implementation.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
 *
 * @internal
 */
class TextBundleWriter implements BundleWriterInterface
{
    /**
     * {@inheritdoc}
     */
    public function write($path, $locale, $data, $fallback = true)
    {
        $file = fopen($path.'/'.$locale.'.txt', 'w');

        $this->writeResourceBundle($file, $locale, $data, $fallback);

        fclose($file);
    }

    /**
     * Writes a "resourceBundle" node.
     *
     * @param resource $file       The file handle to write to
     * @param string   $bundleName The name of the bundle
     * @param mixed    $value      The value of the node
     * @param bool     $fallback   Whether the resource bundle should be merged
     *                             with the fallback locale
     *
     * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
     */
    private function writeResourceBundle($file, $bundleName, $value, $fallback)
    {
        fwrite($file, $bundleName);

        $this->writeTable($file, $value, 0, $fallback);

        fwrite($file, "\n");
    }

    /**
     * Writes a "resource" node.
     *
     * @param resource $file          The file handle to write to
     * @param mixed    $value         The value of the node
     * @param int      $indentation   The number of levels to indent
     * @param bool     $requireBraces Whether to require braces to be printedaround the value
     *
     * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
     */
    private function writeResource($file, $value, $indentation, $requireBraces = true)
    {
        if (is_int($value)) {
            $this->writeInteger($file, $value);

            return;
        }

        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        if (is_array($value)) {
            $intValues = count($value) === count(array_filter($value, 'is_int'));

            $keys = array_keys($value);

            // check that the keys are 0-indexed and ascending
            $intKeys = $keys === range(0, count($keys) - 1);

            if ($intValues && $intKeys) {
                $this->writeIntVector($file, $value, $indentation);

                return;
            }

            if ($intKeys) {
                $this->writeArray($file, $value, $indentation);

                return;
            }

            $this->writeTable($file, $value, $indentation);

            return;
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        $this->writeString($file, (string) $value, $requireBraces);
    }

    /**
     * Writes an "integer" node.
     *
     * @param resource $file  The file handle to write to
     * @param int      $value The value of the node
     *
     * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
     */
    private function writeInteger($file, $value)
    {
        fprintf($file, ':int{%d}', $value);
    }

    /**
     * Writes an "intvector" node.
     *
     * @param resource $file        The file handle to write to
     * @param array    $value       The value of the node
     * @param int      $indentation The number of levels to indent
     *
     * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
     */
    private function writeIntVector($file, array $value, $indentation)
    {
        fwrite($file, ":intvector{\n");

        foreach ($value as $int) {
            fprintf($file, "%s%d,\n", str_repeat('    ', $indentation + 1), $int);
        }

        fprintf($file, '%s}', str_repeat('    ', $indentation));
    }

    /**
     * Writes a "string" node.
     *
     * @param resource $file          The file handle to write to
     * @param string   $value         The value of the node
     * @param bool     $requireBraces Whether to require braces to be printed
     *                                around the value
     *
     * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
     */
    private function writeString($file, $value, $requireBraces = true)
    {
        if ($requireBraces) {
            fprintf($file, '{"%s"}', $value);

            return;
        }

        fprintf($file, '"%s"', $value);
    }

    /**
     * Writes an "array" node.
     *
     * @param resource $file        The file handle to write to
     * @param array    $value       The value of the node
     * @param int      $indentation The number of levels to indent
     *
     * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
     */
    private function writeArray($file, array $value, $indentation)
    {
        fwrite($file, "{\n");

        foreach ($value as $entry) {
            fwrite($file, str_repeat('    ', $indentation + 1));

            $this->writeResource($file, $entry, $indentation + 1, false);

            fwrite($file, ",\n");
        }

        fprintf($file, '%s}', str_repeat('    ', $indentation));
    }

    /**
     * Writes a "table" node.
     *
     * @param resource $file        The file handle to write to
     * @param iterable $value       The value of the node
     * @param int      $indentation The number of levels to indent
     * @param bool     $fallback    Whether the table should be merged
     *                              with the fallback locale
     *
     * @throws UnexpectedTypeException when $value is not an array and not a
     *                                 \Traversable instance
     */
    private function writeTable($file, $value, $indentation, $fallback = true)
    {
        if (!is_array($value) && !$value instanceof \Traversable) {
            throw new UnexpectedTypeException($value, 'array or \Traversable');
        }

        if (!$fallback) {
            fwrite($file, ':table(nofallback)');
        }

        fwrite($file, "{\n");

        foreach ($value as $key => $entry) {
            fwrite($file, str_repeat('    ', $indentation + 1));

            // escape colons, otherwise they are interpreted as resource types
            if (false !== strpos($key, ':') || false !== strpos($key, ' ')) {
                $key = '"'.$key.'"';
            }

            fwrite($file, $key);

            $this->writeResource($file, $entry, $indentation + 1);

            fwrite($file, "\n");
        }

        fprintf($file, '%s}', str_repeat('    ', $indentation));
    }
}
