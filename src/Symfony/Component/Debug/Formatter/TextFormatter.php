<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Formatter;

use Symfony\Component\Debug\Exception\FlattenException;

/**
 * TextFormatter formats an exception as a plain-text string.
 */
class TextFormatter implements FormatterInterface
{
    private $charset = 'UTF-8';

    /**
     * {@inheritdoc}
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType()
    {
        return 'text/plain; charset='.$this->charset;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(FlattenException $exception, $debug)
    {
        $content = '';

        switch ($exception->getStatusCode()) {
            case 404:
                $title = 'Sorry, the page you are looking for could not be found.';
                break;
            default:
                $title = 'Whoops, looks like something went wrong.';
        }

        if ($debug) {
            try {
                $count = count($exception->getAllPrevious());
                $total = $count + 1;
                foreach ($exception->toArray() as $position => $e) {
                    $ind = $count - $position + 1;
                    $class = $this->formatClass($e['class']);
                    $message = $this->sanitizeString($e['message']);
                    $path = $this->formatPath($e['trace'][0]['file'], $e['trace'][0]['line']);
                    $trace = $this->formatTrace($e['trace']);
                    $content .= "$ind/$total: $class\n  $message\n\n$trace\n\n";
                }
            } catch (\Exception $e) {
                // something nasty happened and we cannot throw an exception anymore
                $title = sprintf('Exception thrown when handling an exception (%s: %s)', get_class($e), $this->sanitizeString($e->getMessage()));
            }
        }

        return "$title\n\n$content";
    }

    private function formatTrace(array $trace)
    {
        $content = '';
        foreach ($trace as $trace) {
            $line = '';
            if ($trace['function']) {
                $line .= sprintf('at %s%s%s(%s)', $this->formatClass($trace['class']), $trace['type'], $trace['function'], $this->formatArgs($trace['args']));
            }
            if (isset($trace['file']) && isset($trace['line'])) {
                if ($line) {
                    $line .= ' ';
                }
                $line .= $this->formatPath($trace['file'], $trace['line']);
            }
            $content .= "  $line\n";
        }

        return $content;
    }

    private function formatClass($class)
    {
        return $class;
    }

    private function formatPath($path, $line)
    {
        $path = $this->sanitizeString($path);

        return sprintf('in %s:%u', $path, $line);
    }

    /**
     * Formats an array as a string.
     *
     * @param array $args The argument array
     *
     * @return string
     */
    private function formatArgs(array $args)
    {
        $result = array();
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $formattedValue = sprintf('object(%s)', $this->formatClass($item[1]));
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('array(%s)', is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('string' === $item[0]) {
                $formattedValue = sprintf("'%s'", $this->sanitizeString($item[1]));
            } elseif ('null' === $item[0]) {
                $formattedValue = 'null';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = ''.strtolower(var_export($item[1], true)).'';
            } elseif ('resource' === $item[0]) {
                $formattedValue = 'resource';
            } else {
                $formattedValue = str_replace("\n", '', var_export($this->sanitizeString((string) $item[1]), true));
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $this->sanitizeString($key), $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * Removes control characters from a string.
     */
    private function sanitizeString($str)
    {
        return preg_replace('@[\x00-\x1F]+@', ' ', $str);
    }
}
