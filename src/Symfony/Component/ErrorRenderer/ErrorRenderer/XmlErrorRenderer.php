<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\ErrorRenderer;

use Symfony\Component\ErrorRenderer\Exception\FlattenException;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class XmlErrorRenderer implements ErrorRendererInterface
{
    private $debug;
    private $charset;

    public function __construct(bool $debug = false, string $charset = null)
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
        $debug = $this->debug && ($exception->getHeaders()['X-Debug'] ?? true);
        $title = $this->escapeXml($exception->getTitle());
        if ($debug) {
            $message = $this->escapeXml($exception->getMessage());
        } else {
            $message = 404 === $exception->getStatusCode() ? 'Sorry, the page you are looking for could not be found.' : 'Whoops, looks like something went wrong.';
        }
        $statusCode = $this->escapeXml($exception->getStatusCode());
        $charset = $this->escapeXml($this->charset);

        $exceptions = '';
        if ($debug) {
            $exceptions .= '<exceptions>';
            foreach ($exception->toArray() as $e) {
                $exceptions .= sprintf('<exception class="%s" message="%s"><traces>', $e['class'], $this->escapeXml($e['message']));
                foreach ($e['trace'] as $trace) {
                    $exceptions .= '<trace>';
                    if ($trace['function']) {
                        $exceptions .= sprintf('at %s%s%s(%s) ', $trace['class'], $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
                    }
                    if (isset($trace['file'], $trace['line'])) {
                        $exceptions .= $this->formatPath($trace['file'], $trace['line']);
                    }
                    $exceptions .= '</trace>';
                }
                $exceptions .= '</traces></exception>';
            }
            $exceptions .= '</exceptions>';
        }

        return <<<EOF
<?xml version="1.0" encoding="{$charset}" ?>
<problem xmlns="urn:ietf:rfc:7807">
    <title>{$title}</title>
    <status>{$statusCode}</status>
    <detail>{$message}</detail>
    {$exceptions}
</problem>
EOF;
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

        return sprintf('in %s %s', $this->escapeXml($path), 0 < $line ? ' line '.$line : '');
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
