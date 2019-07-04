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
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', $this->charset);
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $this->createElementStartTag($xml, 'problem', '', ['xmlns' => 'urn:ietf:rfc:7807']);
        $this->createElement($xml, 'title', $exception->getTitle());
        $this->createElement($xml, 'status', $exception->getStatusCode());
        $this->createElement($xml, 'detail', $exception->getMessage());

        if ($this->debug) {
            $this->createElementStartTag($xml, 'exceptions');
            foreach ($exception->toArray() as $e) {
                $this->createElementStartTag($xml, 'exception', '', ['class' => $e['class'], 'message' => $e['message']]);

                $this->createElementStartTag($xml, 'traces');
                foreach ($e['trace'] as $trace) {
                    $traceContent = '';
                    if ($trace['function']) {
                        $traceContent = sprintf('at %s%s%s(%s)', $trace['class'], $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
                    }
                    if (isset($trace['file'], $trace['line'])) {
                        $traceContent .= ' '.$this->formatPath($trace['file'], $trace['line']);
                    }

                    $this->createElement($xml, 'trace', $traceContent);
                }
                $xml->endElement(); // </traces>
                $xml->endElement(); // </exception>
            }
            $xml->endElement(); // </exceptions>
        }

        $xml->endElement(); // </problem>
        $xml->endDocument();

        return $xml->outputMemory();
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

    private function createElementStartTag(\XMLWriter $xml, string $name, string $content = '', array $attributes = [])
    {
        $xml->startElement($name);

        foreach ($attributes as $attributeName => $attributeValue) {
            $xml->startAttribute($attributeName);
            $xml->text($attributeValue);
            $xml->endAttribute();
        }

        if ('' !== $content) {
            $xml->text($this->escapeXml($content));
        }
    }

    private function createElement(\XMLWriter $xml, string $name, string $content = '', array $attributes = [])
    {
        $this->createElementStartTag($xml, $name, $content, $attributes);
        $xml->endElement();
    }
}
