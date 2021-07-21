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
final class CodeExtension extends AbstractExtension
{
    private $fileLinkFormat;
    private $charset;
    private $projectDir;

    /**
     * @param string|FileLinkFormatter $fileLinkFormat The format for links to source files
     */
    public function __construct($fileLinkFormat, string $projectDir, string $charset)
    {
        $this->fileLinkFormat = $fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
        $this->projectDir = str_replace('\\', '/', $projectDir).'/';
        $this->charset = $charset;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('abbr_class', [$this, 'abbrClass'], ['is_safe' => ['html']]),
            new TwigFilter('abbr_method', [$this, 'abbrMethod'], ['is_safe' => ['html']]),
            new TwigFilter('format_args', [$this, 'formatArgs'], ['is_safe' => ['html']]),
            new TwigFilter('format_args_as_text', [$this, 'formatArgsAsText']),
            new TwigFilter('file_excerpt', [$this, 'fileExcerpt'], ['is_safe' => ['html']]),
            new TwigFilter('format_file', [$this, 'formatFile'], ['is_safe' => ['html']]),
            new TwigFilter('format_file_from_text', [$this, 'formatFileFromText'], ['is_safe' => ['html']]),
            new TwigFilter('format_log_message', [$this, 'formatLogMessage'], ['is_safe' => ['html']]),
            new TwigFilter('file_link', [$this, 'getFileLink']),
            new TwigFilter('file_relative', [$this, 'getFileRelative']),
        ];
    }

    public function abbrClass(string $class): string
    {
        $parts = explode('\\', $class);
        $short = array_pop($parts);

        return sprintf('<abbr title="%s">%s</abbr>', $class, $short);
    }

    public function abbrMethod(string $method): string
    {
        if (str_contains($method, '::')) {
            [$class, $method] = explode('::', $method, 2);
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
     */
    public function formatArgs(array $args): string
    {
        $result = [];
        foreach ($args as $key => $item) {
            if ('object' === $item[0]) {
                $parts = explode('\\', $item[1]);
                $short = array_pop($parts);
                $formattedValue = sprintf('<em>object</em>(<abbr title="%s">%s</abbr>)', $item[1], $short);
            } elseif ('array' === $item[0]) {
                $formattedValue = sprintf('<em>array</em>(%s)', \is_array($item[1]) ? $this->formatArgs($item[1]) : $item[1]);
            } elseif ('null' === $item[0]) {
                $formattedValue = '<em>null</em>';
            } elseif ('boolean' === $item[0]) {
                $formattedValue = '<em>'.strtolower(var_export($item[1], true)).'</em>';
            } elseif ('resource' === $item[0]) {
                $formattedValue = '<em>resource</em>';
            } else {
                $formattedValue = str_replace("\n", '', htmlspecialchars(var_export($item[1], true), \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset));
            }

            $result[] = \is_int($key) ? $formattedValue : sprintf("'%s' => %s", $key, $formattedValue);
        }

        return implode(', ', $result);
    }

    /**
     * Formats an array as a string.
     */
    public function formatArgsAsText(array $args): string
    {
        return strip_tags($this->formatArgs($args));
    }

    /**
     * Returns an excerpt of a code file around the given line number.
     */
    public function fileExcerpt(string $file, int $line, int $srcContext = 3): ?string
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
                $lines[] = '<li'.($i == $line ? ' class="selected"' : '').'><a class="anchor" id="line'.$i.'"></a><code>'.self::fixCodeMarkup($content[$i - 1]).'</code></li>';
            }

            return '<ol start="'.max($line - $srcContext, 1).'">'.implode("\n", $lines).'</ol>';
        }

        return null;
    }

    /**
     * Formats a file path.
     */
    public function formatFile(string $file, int $line, string $text = null): string
    {
        $file = trim($file);

        if (null === $text) {
            $text = $file;
            if (null !== $rel = $this->getFileRelative($text)) {
                $rel = explode('/', $rel, 2);
                $text = sprintf('<abbr title="%s%2$s">%s</abbr>%s', $this->projectDir, $rel[0], '/'.($rel[1] ?? ''));
            }
        }

        if (0 < $line) {
            $text .= ' at line '.$line;
        }

        if (false !== $link = $this->getFileLink($file, $line)) {
            return sprintf('<a href="%s" title="Click to open this file" class="file_link">%s</a>', htmlspecialchars($link, \ENT_COMPAT | \ENT_SUBSTITUTE, $this->charset), $text);
        }

        return $text;
    }

    /**
     * Returns the link for a given file/line pair.
     *
     * @return string|false A link or false
     */
    public function getFileLink(string $file, int $line)
    {
        if ($fmt = $this->fileLinkFormat) {
            return \is_string($fmt) ? strtr($fmt, ['%f' => $file, '%l' => $line]) : $fmt->format($file, $line);
        }

        return false;
    }

    public function getFileRelative(string $file): ?string
    {
        $file = str_replace('\\', '/', $file);

        if (null !== $this->projectDir && str_starts_with($file, $this->projectDir)) {
            return ltrim(substr($file, \strlen($this->projectDir)), '/');
        }

        return null;
    }

    public function formatFileFromText(string $text): string
    {
        return preg_replace_callback('/in ("|&quot;)?(.+?)\1(?: +(?:on|at))? +line (\d+)/s', function ($match) {
            return 'in '.$this->formatFile($match[2], $match[3]);
        }, $text);
    }

    /**
     * @internal
     */
    public function formatLogMessage(string $message, array $context): string
    {
        if ($context && str_contains($message, '{')) {
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

    protected static function fixCodeMarkup(string $line): string
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
