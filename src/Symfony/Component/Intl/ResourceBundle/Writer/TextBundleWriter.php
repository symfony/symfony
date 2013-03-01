<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Writer;

/**
 * Writes .txt resource bundles.
 *
 * The resulting files can be converted to binary .res files using the
 * {@link \Symfony\Component\Intl\ResourceBundle\Transformer\BundleCompiler}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
 */
class TextBundleWriter implements BundleWriterInterface
{
    /**
     * {@inheritdoc}
     */
    public function write($path, $locale, $data)
    {
        $file = fopen($path.'/'.$locale.'.txt', 'w');

        $this->writeResourceBundle($file, $locale, $data);

        fclose($file);
    }

    /**
     * Writes a "resourceBundle" node.
     *
     * @param resource $file       The file handle to write to.
     * @param string   $bundleName The name of the bundle.
     * @param mixed    $value      The value of the node.
     *
     * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
     */
    private function writeResourceBundle($file, $bundleName, $value)
    {
        fwrite($file, $bundleName);

        $this->writeTable($file, $value, 0);

        fwrite($file, "\n");
    }

    /**
     * Writes a "resource" node.
     *
     * @param resource $file          The file handle to write to.
     * @param mixed    $value         The value of the node.
     * @param integer  $indentation   The number of levels to indent.
     * @param Boolean  $requireBraces Whether to require braces to be printed
     *                                around the value.
     *
     * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
     */
    private function writeResource($file, $value, $indentation, $requireBraces = true)
    {
        if (is_int($value)) {
            $this->writeInteger($file, $value);

            return;
        }

        if (is_array($value)) {
            if (count($value) === count(array_filter($value, 'is_int'))) {
                $this->writeIntVector($file, $value, $indentation);

                return;
            }

            $keys = array_keys($value);

            if (count($keys) === count(array_filter($keys, 'is_int'))) {
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
     * @param resource $file  The file handle to write to.
     * @param integer  $value The value of the node.
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
     * @param resource $file        The file handle to write to.
     * @param array    $value       The value of the node.
     * @param integer  $indentation The number of levels to indent.
     *
     * @see http://source.icu-project.org/repos/icu/icuhtml/trunk/design/bnf_rb.txt
     */
    private function writeIntVector($file, array $value, $indentation)
    {
        fwrite($file, ":intvector{\n");

        foreach ($value as $int) {
            fprintf($file, "%s%d,\n", str_repeat('    ', $indentation + 1), $int);
        }

        fprintf($file, "%s}", str_repeat('    ', $indentation));
    }

    /**
     * Writes a "string" node.
     *
     * @param resource $file         The file handle to write to.
     * @param string   $value        The value of the node.
     * @param Boolean  $requireBraces Whether to require braces to be printed
     *                                around the value.
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
     * @param resource $file        The file handle to write to.
     * @param array    $value       The value of the node.
     * @param integer  $indentation The number of levels to indent.
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
     * @param resource $file        The file handle to write to.
     * @param array    $value       The value of the node.
     * @param integer  $indentation The number of levels to indent.
     */
    private function writeTable($file, array $value, $indentation)
    {
        fwrite($file, "{\n");

        foreach ($value as $key => $entry) {
            fwrite($file, str_repeat('    ', $indentation + 1));
            fwrite($file, $key);

            $this->writeResource($file, $entry, $indentation + 1);

            fwrite($file, "\n");
        }

        fprintf($file, '%s}', str_repeat('    ', $indentation));
    }
}
