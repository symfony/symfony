<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Encoder;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Encodes CSV data.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CsvEncoder implements EncoderInterface, DecoderInterface
{
    const FORMAT = 'csv';
    const DELIMITER_KEY = 'csv_delimiter';
    const ENCLOSURE_KEY = 'csv_enclosure';
    const ESCAPE_CHAR_KEY = 'csv_escape_char';
    const KEY_SEPARATOR_KEY = 'csv_key_separator';

    private $delimiter;
    private $enclosure;
    private $escapeChar;
    private $keySeparator;

    /**
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escapeChar
     * @param string $keySeparator
     */
    public function __construct($delimiter = ',', $enclosure = '"', $escapeChar = '\\', $keySeparator = '.')
    {
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escapeChar = $escapeChar;
        $this->keySeparator = $keySeparator;
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = array())
    {
        $handle = fopen('php://temp,', 'w+');

        if (!is_array($data)) {
            $data = array(array($data));
        } elseif (empty($data)) {
            $data = array(array());
        } else {
            // Sequential arrays of arrays are considered as collections
            $i = 0;
            foreach ($data as $key => $value) {
                if ($i !== $key || !is_array($value)) {
                    $data = array($data);
                    break;
                }

                ++$i;
            }
        }

        list($delimiter, $enclosure, $escapeChar, $keySeparator) = $this->getCsvOptions($context);

        $headers = null;
        foreach ($data as $value) {
            $result = array();
            $this->flatten($value, $result, $keySeparator);

            if (null === $headers) {
                $headers = array_keys($result);
                fputcsv($handle, $headers, $delimiter, $enclosure, $escapeChar);
            } elseif (array_keys($result) !== $headers) {
                throw new InvalidArgumentException('To use the CSV encoder, each line in the data array must have the same structure. You may want to use a custom normalizer class to normalize the data format before passing it to the CSV encoder.');
            }

            fputcsv($handle, $result, $delimiter, $enclosure, $escapeChar);
        }

        rewind($handle);
        $value = stream_get_contents($handle);
        fclose($handle);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsEncoding($format)
    {
        return self::FORMAT === $format;
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format, array $context = array())
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $data);
        rewind($handle);

        $headers = null;
        $nbHeaders = 0;
        $result = array();

        list($delimiter, $enclosure, $escapeChar, $keySeparator) = $this->getCsvOptions($context);

        while (false !== ($cols = fgetcsv($handle, 0, $delimiter, $enclosure, $escapeChar))) {
            $nbCols = count($cols);

            if (null === $headers) {
                $nbHeaders = $nbCols;

                foreach ($cols as $col) {
                    $headers[] = explode($keySeparator, $col);
                }

                continue;
            }

            $item = array();
            for ($i = 0; ($i < $nbCols) && ($i < $nbHeaders); ++$i) {
                $depth = count($headers[$i]);
                $arr = &$item;
                for ($j = 0; $j < $depth; ++$j) {
                    // Handle nested arrays
                    if ($j === ($depth - 1)) {
                        $arr[$headers[$i][$j]] = $cols[$i];

                        continue;
                    }

                    if (!isset($arr[$headers[$i][$j]])) {
                        $arr[$headers[$i][$j]] = array();
                    }

                    $arr = &$arr[$headers[$i][$j]];
                }
            }

            $result[] = $item;
        }
        fclose($handle);

        if (empty($result) || isset($result[1])) {
            return $result;
        }

        // If there is only one data line in the document, return it (the line), the result is not considered as a collection
        return $result[0];
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding($format)
    {
        return self::FORMAT === $format;
    }

    /**
     * Flattens an array and generates keys including the path.
     *
     * @param array  $array
     * @param array  $result
     * @param string $keySeparator
     * @param string $parentKey
     */
    private function flatten(array $array, array &$result, $keySeparator, $parentKey = '')
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->flatten($value, $result, $keySeparator, $parentKey.$key.$keySeparator);
            } else {
                $result[$parentKey.$key] = $value;
            }
        }
    }

    private function getCsvOptions(array $context)
    {
        $delimiter = isset($context[self::DELIMITER_KEY]) ? $context[self::DELIMITER_KEY] : $this->delimiter;
        $enclosure = isset($context[self::ENCLOSURE_KEY]) ? $context[self::ENCLOSURE_KEY] : $this->enclosure;
        $escapeChar = isset($context[self::ESCAPE_CHAR_KEY]) ? $context[self::ESCAPE_CHAR_KEY] : $this->escapeChar;
        $keySeparator = isset($context[self::KEY_SEPARATOR_KEY]) ? $context[self::KEY_SEPARATOR_KEY] : $this->keySeparator;

        return array($delimiter, $enclosure, $escapeChar, $keySeparator);
    }
}
