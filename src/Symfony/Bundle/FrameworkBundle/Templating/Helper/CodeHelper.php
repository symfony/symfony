<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Component\Templating\Helper\Helper;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * CodeHelper.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CodeHelper extends Helper
{
    protected $fileLinkFormat;
    protected $rootDir;

    /**
     * Constructor.
     *
     * @param string $fileLinkFormat The format for links to source files
     * @param string $rootDir        The project root directory
     */
    public function __construct($fileLinkFormat, $rootDir)
    {
        $this->fileLinkFormat = null !== $fileLinkFormat ? $fileLinkFormat : ini_get('xdebug.file_link_format');
        $this->rootDir = str_replace('\\', '/', $rootDir).'/';
    }

    /**
     * Formats an array as a string.
     *
     * @param array $args The argument array
     *
     * @return string
     */
    public function formatArgsAsText($args)
    {
        $result = array();
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $formattedValue = sprintf("object(%s)", $item[1]);
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf("array(%s)", $this->formatArgsAsText($item[1]));
            } elseif ('string'  === $item[0]) {
                $formattedValue = sprintf("'%s'", $item[1]);
            } elseif ('null' === $item[0]) {
                $formattedValue = 'null';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = strtolower(var_export($item[1], true));
            } elseif ('resource' === $item[0]) {
                $formattedValue = 'resource';
            } else {
                $formattedValue = str_replace("\n", '', var_export((string) $item[1], true));
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }

    public function abbrClass($class)
    {
        $parts = explode('\\', $class);
        $short = array_pop($parts);

        return sprintf("<abbr title=\"%s\">%s</abbr>", $class, $short);
    }

    public function abbrMethod($method)
    {
        list($class, $method) = explode('::', $method);

        $parts = explode('\\', $class);
        $short = array_pop($parts);

        return sprintf("<abbr title=\"%s\">%s</abbr>::%s", $class, $short, $method);
    }

    /**
     * Formats an array as a string.
     *
     * @param array $args The argument array
     *
     * @return string
     */
    public function formatArgs($args)
    {
        $result = array();
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $parts = explode('\\', $item[1]);
                $short = array_pop($parts);
                $formattedValue = sprintf("<em>object</em>(<abbr title=\"%s\">%s</abbr>)", $item[1], $short);
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf("<em>array</em>(%s)", $this->formatArgs($item[1]));
            } elseif ('string'  === $item[0]) {
                $formattedValue = sprintf("'%s'", $item[1]);
            } elseif ('null' === $item[0]) {
                $formattedValue = '<em>null</em>';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = '<em>'.strtolower(var_export($item[1], true)).'</em>';
            } elseif ('resource' === $item[0]) {
                $formattedValue = '<em>resource</em>';
            } else {
                $formattedValue = str_replace("\n", '', var_export((string) $item[1], true));
            }

            $result[] = is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * Returns an excerpt of a code file around the given line number.
     *
     * @param string $file  A file path
     * @param int    $line  The selected line number
     *
     * @return string An HTML string
     */
    public function fileExcerpt($file, $line)
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
     * Formats a file path.
     *
     * @param  string  $file   An absolute file path
     * @param  integer $line   The line number
     * @param  string  $format The output format (txt or html)
     * @param  string  $text   Use this text for the link rather than the file path
     *
     * @return string
     */
    public function formatFile($file, $line)
    {
        if (0 === strpos($file, $this->rootDir)) {
            $file = str_replace($this->rootDir, '', str_replace('\\', '/', $file));
            $file = sprintf('<abbr title="%s">kernel.root_dir</abbr>/%s', $this->rootDir, $file);
        }

        if (!$this->fileLinkFormat) {
            return "$file line $line";
        }

        $link = strtr($this->fileLinkFormat, array('%f' => $file, '%l' => $line));

        return sprintf('<a href="%s" title="Click to open this file" class="file_link">%s line %s</a>', $link, $file, $line);
    }

    public function formatFileFromText($text)
    {
        $that = $this;

        return preg_replace_callback('/(called|defined) in (.*?)(?: on)? line (\d+)/', function ($match) use ($that) {
            return $match[1].' in '.$that->formatFile($match[2], $match[3]);
        }, $text);
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'code';
    }
}
