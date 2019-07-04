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
        $problemElement = new \SimpleXMLElement('<problem/>');
        $problemElement->addAttribute('xmlns', 'urn:ietf:rfc:7807');

        $problemElement->addChild('title', $this->escapeXml($exception->getTitle()));
        $problemElement->addChild('status', $exception->getStatusCode());
        $problemElement->addChild('detail', $this->escapeXml($exception->getMessage()));

        if ($this->debug) {
            $exceptionsElement = $problemElement->addChild('exceptions');
            foreach ($exception->toArray() as $e) {
                $exceptionElement = $exceptionsElement->addChild('exception');
                $exceptionElement->addAttribute('class', $e['class']);
                $exceptionElement->addAttribute('message', $this->escapeXml($e['message']));

                $tracesElement = $exceptionElement->addChild('traces');
                foreach ($e['trace'] as $trace) {
                    $traceContent = '';
                    if ($trace['function']) {
                        $traceContent = sprintf('at %s%s%s(%s)', $trace['class'], $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
                    }
                    if (isset($trace['file'], $trace['line'])) {
                        $traceContent .= ' '.$this->formatPath($trace['file'], $trace['line']);
                    }

                    $tracesElement->addChild('trace', $this->escapeXml($traceContent));
                }
            }
        }

        $xml = str_replace('<?xml version="1.0"?>', sprintf('<?xml version="1.0" encoding="%s" ?>', $this->charset), $problemElement->saveXML());

        return sprintf("<?xml version=\"1.0\" encoding=\"%s\" ?>\n%s", $this->charset, $problemElement->saveXML());
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
