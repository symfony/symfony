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
class TxtErrorRenderer implements ErrorRendererInterface
{
    private $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public static function getFormat(): string
    {
        return 'txt';
    }

    /**
     * {@inheritdoc}
     */
    public function render(FlattenException $exception): string
    {
        $debug = $this->debug && ($exception->getHeaders()['X-Debug'] ?? true);

        if ($debug) {
            $message = $exception->getMessage();
        } else {
            $message = 404 === $exception->getStatusCode() ? 'Sorry, the page you are looking for could not be found.' : 'Whoops, looks like something went wrong.';
        }

        $content = sprintf("[title] %s\n", $exception->getTitle());
        $content .= sprintf("[status] %s\n", $exception->getStatusCode());
        $content .= sprintf("[detail] %s\n", $message);

        if ($debug) {
            foreach ($exception->toArray() as $i => $e) {
                $content .= sprintf("[%d] %s: %s\n", $i + 1, $e['class'], $e['message']);
                foreach ($e['trace'] as $trace) {
                    if ($trace['function']) {
                        $content .= sprintf('at %s%s%s(%s) ', $trace['class'], $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
                    }
                    if (isset($trace['file'], $trace['line'])) {
                        $content .= $this->formatPath($trace['file'], $trace['line']);
                    }
                    $content .= "\n";
                }
            }
        }

        return $content;
    }

    private function formatPath(string $path, int $line): string
    {
        $file = preg_match('#[^/\\\\]*+$#', $path, $file) ? $file[0] : $path;

        return sprintf('in %s %s', $path, 0 < $line ? ' line '.$line : '');
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
                $formattedValue = str_replace("\n", '', var_export($item[1], true));
            }

            $result[] = \is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }
}
