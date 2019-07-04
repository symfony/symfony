<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorCatcher\ErrorRenderer;

use Symfony\Component\ErrorCatcher\Exception\FlattenException;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class XmlErrorRenderer implements ErrorRendererInterface
{
    private $debug;
    private $charset;

    public function __construct(bool $debug = true, string $charset = null)
    {
        $this->debug = $debug;
        $this->charset = $charset ?: (ini_get('default_charset') ?: 'UTF-8');
    }

    /**
     * {@inheritdoc}
     */
    public static function getFormat(): string
    {
        return 'xml';
    }

    /**
     * {@inheritdoc}
     */
    public function render(FlattenException $exception): string
    {
        $xmlDocument = new \DOMDocument('1.0', $this->charset);

        $problemElement = $xmlDocument->createElement('problem');
        $problemElement->setAttribute('xmlns', 'urn:ietf:rfc:7807');
        $xmlDocument->appendChild($problemElement);

        $titleElement = $xmlDocument->createElement('title', $this->escapeXml($exception->getTitle()));
        $statusElement = $xmlDocument->createElement('status', $exception->getStatusCode());
        $detailElement = $xmlDocument->createElement('detail', $this->escapeXml($exception->getMessage()));
        $problemElement->appendChild($titleElement);
        $problemElement->appendChild($statusElement);
        $problemElement->appendChild($detailElement);

        if ($this->debug) {
            $exceptionsElement = $xmlDocument->createElement('exceptions');
            foreach ($exception->toArray() as $e) {
                $exceptionElement = $xmlDocument->createElement('exception');
                $exceptionElement->setAttribute('class', $e['class']);
                $exceptionElement->setAttribute('message', $this->escapeXml($e['message']));

                $tracesElement = $xmlDocument->createElement('traces');
                foreach ($e['trace'] as $trace) {
                    $traceContent = '';
                    if ($trace['function']) {
                        $traceContent = sprintf('at %s%s%s(%s)', $trace['class'], $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
                    }
                    if (isset($trace['file'], $trace['line'])) {
                        $traceContent .= ' '.$this->formatPath($trace['file'], $trace['line']);
                    }

                    $traceElement =  $xmlDocument->createElement('trace', $this->escapeXml($traceContent));
                    $tracesElement->appendChild($traceElement);
                }

                $exceptionElement->appendChild($tracesElement);
                $exceptionsElement->appendChild($exceptionElement);
            }
        }

        $problemElement->appendChild($exceptionsElement);

        $xmlDocument->preserveWhiteSpace = false;
        $xmlDocument->formatOutput = true;

        return $xmlDocument->saveXML();
    }

    /**
     * XML-encodes a string.
     */
    private function escapeXml(string $str): string
    {
        return htmlspecialchars($str, ENT_COMPAT | ENT_SUBSTITUTE, $this->charset);
    }

    private function formatPath(string $path, int $line): string
    {
        $file = $this->escapeXml(preg_match('#[^/\\\\]*+$#', $path, $file) ? $file[0] : $path);

        return sprintf('in %s %s', $this->escapeXml($path), 0 < $line ? 'line '.$line : '');
    }

    /**
     * Formats an array as a string.
     */
    private function formatArgs(array $args): string
    {
        $result = [];
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $formattedValue = sprintf('object(%s)', $item[1]);
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('array(%s)', \is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('null' === $item[0]) {
                $formattedValue = 'null';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = strtolower(var_export($item[1], true));
            } elseif ('resource' === $item[0]) {
                $formattedValue = 'resource';
            } else {
                $formattedValue = str_replace("\n", '', $this->escapeXml(var_export($item[1], true)));
            }

            $result[] = \is_int($key) ? $formattedValue : sprintf("'%s' => %s", $this->escapeXml($key), $formattedValue);
        }

        return implode(', ', $result);
    }
}
