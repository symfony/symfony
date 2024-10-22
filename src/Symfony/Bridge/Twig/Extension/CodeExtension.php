<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension relate to PHP code and used by the profiler and the default exception templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CodeExtension extends AbstractExtension
{
    private $fileLinkFormat;
    private $rootDir;
    private $charset;

    /**
     * @param string|FileLinkFormatter $fileLinkFormat The format for links to source files
     * @param string                   $rootDir        The project root directory
     * @param string                   $charset        The charset
     */
    public function __construct($fileLinkFormat, $rootDir, $charset)
    {
        $this->fileLinkFormat = $fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
        $this->rootDir = str_replace('/', \DIRECTORY_SEPARATOR, \dirname($rootDir)).\DIRECTORY_SEPARATOR;
        $this->charset = $charset;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('abbr_class', [$this, 'abbrClass'], ['is_safe' => ['html'], 'pre_escape' => 'html']),
            new TwigFilter('abbr_method', [$this, 'abbrMethod'], ['is_safe' => ['html'], 'pre_escape' => 'html']),
            new TwigFilter('format_args', [$this, 'formatArgs'], ['is_safe' => ['html']]),
            new TwigFilter('format_args_as_text', [$this, 'formatArgsAsText']),
            new TwigFilter('file_excerpt', [$this, 'fileExcerpt'], ['is_safe' => ['html']]),
            new TwigFilter('format_file', [$this, 'formatFile'], ['is_safe' => ['html']]),
            new TwigFilter('format_file_from_text', [$this, 'formatFileFromText'], ['is_safe' => ['html']]),
            new TwigFilter('format_log_message', [$this, 'formatLogMessage'], ['is_safe' => ['html']]),
            new TwigFilter('file_link', [$this, 'getFileLink']),
        ];
    }

    public function abbrClass($class)
    {
        $parts = explode('\\', $class);
        $short = array_pop($parts);

        return sprintf('<abbr title="%s">%s</abbr>', $class, $short);
    }

    public function abbrMethod($method)
    {
        if (false !== strpos($method, '::')) {
            list($class, $method) = explode('::', $method, 2);
            $result = sprintf('%s::%s()', $this->abbrClass($class), $method);
        } elseif ('Closure' === $method) {
            $result = sprintf('<abbr title="%s">%1$s</abbr>', $method);
        } else {
            $result = sprintf('<abbr title="%s">%1$s</abbr>()', $method);
        }

        return $result;
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
        $result = [];
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $parts = explode('\\', $item[1]);
                $short = array_pop($parts);
                $formattedValue = sprintf('<em>object</em>(<abbr title="%s">%s</abbr>)', $item[1], $short);
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('<em>array</em>(%s)', \is_array($item[1]) ? $this->formatArgs($item[1]) : htmlspecialchars(var_export($item[1], true), \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset));
            } elseif ('null' === $item[0]) {
                $formattedValue = '<em>null</em>';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = '<em>'.strtolower(htmlspecialchars(var_export($item[1], true), \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset)).'</em>';
            } elseif ('resource' === $item[0]) {
                $formattedValue = '<em>resource</em>';
            } else {
                $formattedValue = str_replace("\n", '', htmlspecialchars(var_export($item[1], true), \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset));
            }
            $result[] = \is_int($key) ? $formattedValue : sprintf("'%s' => %s", htmlspecialchars($key, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset), $formattedValue);
        }

        return implode(', ', $result);
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
        return strip_tags($this->formatArgs($args));
    }

    /**
     * Returns an excerpt of a code file around the given line number.
     *
     * @param string $file       A file path
     * @param int    $line       The selected line number
     * @param int    $srcContext The number of displayed lines around or -1 for the whole file
     *
     * @return string An HTML string
     */
    public function fileExcerpt($file, $line, $srcContext = 3)
    {
        if (is_file($file) && is_readable($file)) {
            // highlight_file could throw warnings
            // see https://bugs.php.net/25725
            $code = @highlight_file($file, true);
            // remove main code/span tags
            $code = preg_replace('#^<code.*?>\s*<span.*?>(.*)</span>\s*</code>#s', '\\1', $code);
            // split multiline spans
            $code = preg_replace_callback('#<span ([^>]++)>((?:[^<]*+<br \/>)++[^<]*+)</span>#', function ($m) {
                return "<span $m[1]>".str_replace('<br />', "</span><br /><span $m[1]>", $m[2]).'</span>';
            }, $code);
            $content = explode('<br />', $code);

            $lines = [];
            if (0 > $srcContext) {
                $srcContext = \count($content);
            }

            for ($i = max($line - $srcContext, 1), $max = min($line + $srcContext, \count($content)); $i <= $max; ++$i) {
                $lines[] = '<li'.($i == $line ? ' class="selected"' : '').'><a class="anchor" name="line'.$i.'"></a><code>'.self::fixCodeMarkup($content[$i - 1]).'</code></li>';
            }

            return '<ol start="'.max($line - $srcContext, 1).'">'.implode("\n", $lines).'</ol>';
        }

        return null;
    }

    /**
     * Formats a file path.
     *
     * @param string $file An absolute file path
     * @param int    $line The line number
     * @param string $text Use this text for the link rather than the file path
     *
     * @return string
     */
    public function formatFile($file, $line, $text = null)
    {
        $file = trim($file);

        if (null === $text) {
            if (null !== $rel = $this->getFileRelative($file)) {
                $rel = explode('/', htmlspecialchars($rel, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset), 2);
                $text = sprintf('<abbr title="%s%2$s">%s</abbr>%s', htmlspecialchars($this->projectDir, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset), $rel[0], '/'.($rel[1] ?? ''));
            } else {
                $text = htmlspecialchars($file, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
            }
        } else {
            $text = htmlspecialchars($text, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
        }

        if (0 < $line) {
            $text .= ' at line '.$line;
        }

        if (false !== $link = $this->getFileLink($file, $line)) {
            return sprintf('<a href="%s" title="Click to open this file" class="file_link">%s</a>', htmlspecialchars($link, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset), $text);
        }

        return $text;
    }

    public function getFileRelative(string $file)
    {
        $file = str_replace('\\', '/', $file);

        if (null !== $this->projectDir && str_starts_with($file, $this->projectDir)) {
            return ltrim(substr($file, \strlen($this->projectDir)), '/');
        }

        return null;
    }

    /**
     * Returns the link for a given file/line pair.
     *
     * @param string $file An absolute file path
     * @param int    $line The line number
     *
     * @return string|false A link or false
     */
    public function getFileLink($file, $line)
    {
        if ($fmt = $this->fileLinkFormat) {
            return \is_string($fmt) ? strtr($fmt, ['%f' => $file, '%l' => $line]) : $fmt->format($file, $line);
        }

        return false;
    }

    public function formatFileFromText($text)
    {
        return preg_replace_callback('/in ("|&quot;)?(.+?)\1(?: +(?:on|at))? +line (\d+)/s', function ($match) {
            return 'in '.$this->formatFile($match[2], $match[3]);
        }, $text);
    }

    /**
     * @internal
     */
    public function formatLogMessage($message, array $context)
    {
        if ($context && false !== strpos($message, '{')) {
            $replacements = [];
            foreach ($context as $key => $val) {
                if (is_scalar($val)) {
                    $replacements['{'.$key.'}'] = $val;
                }
            }

            if ($replacements) {
                $message = strtr($message, $replacements);
            }
        }

        return htmlspecialchars($message, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'code';
    }

    protected static function fixCodeMarkup($line)
    {
        // </span> ending tag from previous line
        $opening = strpos($line, '<span');
        $closing = strpos($line, '</span>');
        if (false !== $closing && (false === $opening || $closing < $opening)) {
            $line = substr_replace($line, '', $closing, 7);
        }

        // missing </span> tag at the end of line
        $opening = strpos($line, '<span');
        $closing = strpos($line, '</span>');
        if (false !== $opening && (false === $closing || $closing > $opening)) {
            $line .= '</span>';
        }

        return trim($line);
    }
}
