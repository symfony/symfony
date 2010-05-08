<?php

namespace Symfony\Framework\WebBundle\Debug;

use Symfony\Components\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ExceptionFormatter.
 *
 * @package    Symfony
 * @subpackage Framework_WebBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ExceptionFormatter
{
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A Container instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns an array of exception traces.
     *
     * @param Exception $exception  An Exception implementation instance
     * @param string    $format     The trace format (txt or html)
     *
     * @return array An array of traces
     */
    public function getTraces(\Exception $exception, $format = 'txt')
    {
        $traceData = $exception->getTrace();
        array_unshift($traceData, array(
            'function' => '',
            'file'     => $exception->getFile() != null ? $exception->getFile() : null,
            'line'     => $exception->getLine() != null ? $exception->getLine() : null,
            'args'     => array(),
        ));

        $traces = array();
        if ($format == 'html') {
            $lineFormat = 'at <strong>%s%s%s</strong>(%s)<br />in <em>%s</em> line %s <a href="#" onclick="toggle(\'%s\'); return false;">...</a><br /><ul class="code" id="%s" style="display: %s">%s</ul>';
        } else {
            $lineFormat = 'at %s%s%s(%s) in %s line %s';
        }

        for ($i = 0, $count = count($traceData); $i < $count; $i++) {
            $line = isset($traceData[$i]['line']) ? $traceData[$i]['line'] : null;
            $file = isset($traceData[$i]['file']) ? $traceData[$i]['file'] : null;
            $args = isset($traceData[$i]['args']) ? $traceData[$i]['args'] : array();
            $traces[] = sprintf($lineFormat,
                (isset($traceData[$i]['class']) ? $traceData[$i]['class'] : ''),
                (isset($traceData[$i]['type']) ? $traceData[$i]['type'] : ''),
                $traceData[$i]['function'],
                $this->formatArgs($args, false, $format),
                $this->formatFile($file, $line, $format, null === $file ? 'n/a' : $file),
                null === $line ? 'n/a' : $line,
                'trace_'.$i,
                'trace_'.$i,
                $i == 0 ? 'block' : 'none',
                $this->fileExcerpt($file, $line)
            );
        }

        return $traces;
    }

    /**
     * Returns an excerpt of a code file around the given line number.
     *
     * @param string $file  A file path
     * @param int    $line  The selected line number
     *
     * @return string An HTML string
     */
    protected function fileExcerpt($file, $line)
    {
        if (is_readable($file)) {
            $content = preg_split('#<br />#', highlight_file($file, true));

            $lines = array();
            for ($i = max($line - 3, 1), $max = min($line + 3, count($content)); $i <= $max; $i++) {
                $lines[] = '<li'.($i == $line ? ' class="selected"' : '').'>'.$content[$i - 1].'</li>';
            }

            return '<ol start="'.max($line - 3, 1).'">'.implode("\n", $lines).'</ol>';
        }
    }

    /**
     * Formats an array as a string.
     *
     * @param array   $args     The argument array
     * @param boolean $single
     * @param string  $format   The format string (html or txt)
     *
     * @return string
     */
    protected function formatArgs($args, $single = false, $format = 'html')
    {
        $result = array();

        $single and $args = array($args);

        foreach ($args as $key => $value) {
            if (is_object($value)) {
                $formattedValue = ($format == 'html' ? '<em>object</em>' : 'object').sprintf("('%s')", get_class($value));
            } else if (is_array($value)) {
                $formattedValue = ($format == 'html' ? '<em>array</em>' : 'array').sprintf("(%s)", $this->formatArgs($value));
            } else if (is_string($value)) {
                $formattedValue = ($format == 'html' ? sprintf("'%s'", $this->escape($value)) : "'$value'");
            } else if (null === $value) {
                $formattedValue = ($format == 'html' ? '<em>null</em>' : 'null');
            } else {
                $formattedValue = $value;
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $this->escape($key), $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * Formats a file path.
     *
     * @param  string  $file   An absolute file path
     * @param  integer $line   The line number
     * @param  string  $format The output format (txt or html)
     * @param  string  $text   Use this text for the link rather than the file path
     *
     * @return string
     */
    protected function formatFile($file, $line, $format = 'html', $text = null)
    {
        if (null === $text) {
            $text = $file;
        }

        $linkFormat = $this->container->hasParameter('debug.file_link_format') ? $this->container->getParameter('debug.file_link_format') : ini_get('xdebug.file_link_format');
        if ('html' === $format && $file && $line && $linkFormat) {
            $link = strtr($linkFormat, array('%f' => $file, '%l' => $line));
            $text = sprintf('<a href="%s" title="Click to open this file" class="file_link">%s</a>', $link, $text);
        }

        return $text;
    }

    /**
     * Escapes a string value with html entities
     *
     * @param  string  $value
     *
     * @return string
     */
    protected function escape($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        return htmlspecialchars($value, ENT_QUOTES, $this->container->getParameter('kernel.charset'));
    }
}
