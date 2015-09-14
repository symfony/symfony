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
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Debug\Utils\HtmlUtils;

/**
 * Twig extension relate to PHP code and used by the profiler and the default exception templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CodeExtension extends \Twig_Extension
{
    private $fileLinkFormat;
    private $rootDir;
    private $charset;
    private $htmlUtils;

    /**
     * Constructor.
     *
     * @param string $fileLinkFormat The format for links to source files
     * @param string $rootDir        The project root directory
     * @param string $charset        The charset
     */
    public function __construct($fileLinkFormat, $rootDir, $charset, HtmlUtils $htmlUtils = null)
    {
        $this->fileLinkFormat = $fileLinkFormat ?: ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format');
        $this->rootDir = str_replace('/', DIRECTORY_SEPARATOR, dirname($rootDir)).DIRECTORY_SEPARATOR;
        $this->charset = $charset;
        $this->htmlUtils = $htmlUtils;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('abbr_class', array($this, 'abbrClass'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('abbr_method', array($this, 'abbrMethod'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('format_args', array($this, 'formatArgs'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('format_args_as_text', array($this, 'formatArgsAsText')),
            new \Twig_SimpleFilter('file_excerpt', array($this, 'fileExcerpt'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('format_file', array($this, 'formatFile'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('format_file_from_text', array($this, 'formatFileFromText'), array('is_safe' => array('html'))),
            new \Twig_SimpleFilter('file_link', array($this, 'getFileLink')),
        );
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
            $result = sprintf('<abbr title="%s">%s</abbr>', $method, $method);
        } else {
            $result = sprintf('<abbr title="%s">%s</abbr>()', $method, $method);
        }

        return $result;
    }

    /**
     * Formats an array as a string.
     *
     * @param array                 $args      The argument array
     * @param FlattenException|null $exception The flatten exception
     *
     * @return string
     */
    public function formatArgs($args, $exception = null)
    {
        return $this->htmlUtils->formatArgs($args, $exception);
    }

    /**
     * Formats an array as a string.
     *
     * @param array                 $args      The argument array
     * @param FlattenException|null $exception The flatten exception
     *
     * @return string
     */
    public function formatArgsAsText($args, $exception = null)
    {
        return strip_tags($this->formatArgs($args, $exception));
    }

    /**
     * Returns an excerpt of a code file around the given line number.
     *
     * @param string $file A file path
     * @param int    $line The selected line number
     *
     * @return string An HTML string
     */
    public function fileExcerpt($file, $line)
    {
        if (is_readable($file)) {
            // highlight_file could throw warnings
            // see https://bugs.php.net/bug.php?id=25725
            $code = @highlight_file($file, true);
            // remove main code/span tags
            $code = preg_replace('#^<code.*?>\s*<span.*?>(.*)</span>\s*</code>#s', '\\1', $code);
            $content = preg_split('#<br />#', $code);

            $lines = array();
            for ($i = max($line - 3, 1), $max = min($line + 3, count($content)); $i <= $max; ++$i) {
                $lines[] = '<li'.($i == $line ? ' class="selected"' : '').'><code>'.self::fixCodeMarkup($content[$i - 1]).'</code></li>';
            }

            return '<ol start="'.max($line - 3, 1).'">'.implode("\n", $lines).'</ol>';
        }
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
            $text = str_replace('/', DIRECTORY_SEPARATOR, $file);
            if (0 === strpos($text, $this->rootDir)) {
                $text = substr($text, strlen($this->rootDir));
                $text = explode(DIRECTORY_SEPARATOR, $text, 2);
                $text = sprintf('<abbr title="%s%2$s">%s</abbr>%s', $this->rootDir, $text[0], isset($text[1]) ? DIRECTORY_SEPARATOR.$text[1] : '');
            }
        }

        $text = "$text at line $line";

        if (false !== $link = $this->getFileLink($file, $line)) {
            if (PHP_VERSION_ID >= 50400) {
                $flags = ENT_QUOTES | ENT_SUBSTITUTE;
            } else {
                $flags = ENT_QUOTES;
            }

            return sprintf('<a href="%s" title="Click to open this file" class="file_link">%s</a>', htmlspecialchars($link, $flags, $this->charset), $text);
        }

        return $text;
    }

    /**
     * Returns the link for a given file/line pair.
     *
     * @param string $file An absolute file path
     * @param int    $line The line number
     *
     * @return string A link of false
     */
    public function getFileLink($file, $line)
    {
        if ($this->fileLinkFormat && is_file($file)) {
            return strtr($this->fileLinkFormat, array('%f' => $file, '%l' => $line));
        }

        return false;
    }

    public function formatFileFromText($text)
    {
        $that = $this;

        return preg_replace_callback('/in ("|&quot;)?(.+?)\1(?: +(?:on|at))? +line (\d+)/s', function ($match) use ($that) {
            return 'in '.$that->formatFile($match[2], $match[3]);
        }, $text);
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

        return $line;
    }
}
