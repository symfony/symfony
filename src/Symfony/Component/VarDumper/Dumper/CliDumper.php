<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Dumper;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Cursor;

/**
 * CliDumper dumps variables for command line output.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class CliDumper extends AbstractDumper
{
    public static $defaultColors;
    public static $defaultOutputStream = 'php://stdout';

    protected $colors;
    protected $maxStringWidth = 0;
    protected $styles = array(
        // See http://en.wikipedia.org/wiki/ANSI_escape_code#graphics
        'num'       => '1;38;5;33',
        'const'     => '1;38;5;33',
        'str'       => '1;38;5;37',
        'cchr'      => '7',
        'note'      => '38;5;178',
        'ref'       => '38;5;240',
        'solo-ref'  => '38;5;240',
        'public'    => '38;5;28',
        'protected' => '38;5;166',
        'private'   => '38;5;160',
        'meta'      => '38;5;27',
    );

    protected static $controlChars = array(
        "\x1B", // ESC must be the first
        "\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
        "\x08", "\x09", "\x0A", "\x0B", "\x0C", "\x0D", "\x0E", "\x0F",
        "\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
        "\x18", "\x19", "\x1A", "\x1C", "\x1D", "\x1E", "\x1F", "\x7F",
    );

    /**
     * Enables/disables colored output.
     *
     * @param bool $colors
     */
    public function setColors($colors)
    {
        $this->colors = (bool) $colors;
    }

    /**
     * Sets the maximum number of characters per line for dumped strings.
     *
     * @param int $maxStringWidth
     */
    public function setMaxStringWidth($maxStringWidth)
    {
        if (function_exists('iconv')) {
            $this->maxStringWidth = (int) $maxStringWidth;
        }
    }

    /**
     * Configures styles.
     *
     * @param array $styles A map of style namaes to style definitions.
     */
    public function setStyles(array $styles)
    {
        $this->styles = $styles + $this->styles;
    }

    /**
     * {@inheritdoc}
     */
    public function dumpScalar(Cursor $cursor, $type, $val)
    {
        if ('string' === $type) {
            return $this->dumpString($cursor, $val, false, 0);
        }

        $this->dumpKey($cursor);

        $style = 'const';

        switch ($type) {
            case 'integer':
                $style = 'num';
                break;

            case 'double':
                $style = 'num';

                switch (true) {
                    case INF === $val:  $val = 'INF';  break;
                    case -INF === $val: $val = '-INF'; break;
                    case is_nan($val):  $val = 'NAN';  break;
                    default:
                        $val = (string) $val;
                        if (false === strpos($val, $this->decimalPoint)) {
                            $val .= $this->decimalPoint.'0';
                        }
                        break;
                }
                break;

            case 'NULL':
                $val = 'null';
                break;

            case 'boolean':
                $val = $val ? 'true' : 'false';
                break;
        }

        $this->line .= $this->style($style, $val);

        $this->dumpLine($cursor->depth);
    }

    /**
     * {@inheritdoc}
     */
    public function dumpString(Cursor $cursor, $str, $bin, $cut)
    {
        $this->dumpKey($cursor);

        if ('' === $str) {
            $this->line .= '""';
            $this->dumpLine($cursor->depth);
        } else {
            $str = explode("\n", $str);
            $m = count($str) - 1;
            $i = $lineCut = 0;

            if ($bin) {
                $this->line .= 'b';
            }

            if ($m) {
                $this->line .= '"""';
                $this->dumpLine($cursor->depth);
            } else {
                $this->line .= '"';
            }

            foreach ($str as $str) {
                if (0 < $this->maxStringWidth && $this->maxStringWidth < $len = iconv_strlen($str, 'UTF-8')) {
                    $str = iconv_substr($str, 0, $this->maxStringWidth, 'UTF-8');
                    $lineCut = $len - $this->maxStringWidth;
                }

                if ($m) {
                    $this->line .= $this->indentPad;
                }
                $this->line .= $this->style('str', $str);

                if ($i++ == $m) {
                    $this->line .= '"';
                    if ($m) {
                        $this->line .= '""';
                    }
                    if ($cut < 0) {
                        $this->line .= '…';
                        $lineCut = 0;
                    } elseif ($cut) {
                        $lineCut += $cut;
                    }
                }
                if ($lineCut) {
                    $this->line .= '…'.$lineCut;
                    $lineCut = 0;
                }

                $this->dumpLine($cursor->depth);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function enterHash(Cursor $cursor, $type, $class, $hasChild)
    {
        $this->dumpKey($cursor);

        if (Cursor::HASH_OBJECT === $type) {
            $prefix = 'stdClass' !== $class ? $this->style('note', $class).' {' : '{';
        } elseif (Cursor::HASH_RESOURCE === $type) {
            $prefix = $this->style('note', ':'.$class).' {';
        } else {
            $prefix = $class ? $this->style('note', 'array:'.$class).' [' : '[';
        }

        if (Cursor::HASH_RESOURCE === $type) {
            $prefix .= $this->style('ref', '@'.$cursor->softRefHandle);
        } elseif ($cursor->softRefTo) {
            $prefix .= $this->style('ref', '#'.(0 < $cursor->softRefHandle ? $cursor->softRefHandle : $cursor->softRefTo));
        } elseif (0 < $cursor->softRefHandle) {
            $prefix .= $this->style('solo-ref', '#'.$cursor->softRefHandle);
        } elseif ($cursor->hardRefTo && !$cursor->refIndex && $class) {
            $prefix .= $this->style('ref', '&'.$cursor->hardRefTo);
        }

        $this->line .= $prefix;

        if ($hasChild) {
            $this->dumpLine($cursor->depth);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function leaveHash(Cursor $cursor, $type, $class, $hasChild, $cut)
    {
        $this->dumpEllipsis($cursor, $hasChild, $cut);
        $this->line .= Cursor::HASH_OBJECT === $type || Cursor::HASH_RESOURCE === $type ? '}' : ']';
        $this->dumpLine($cursor->depth);
    }

    /**
     * Dumps an ellipsis for cut children.
     *
     * @param Cursor $cursor   The Cursor position in the dump.
     * @param bool   $hasChild When the dump of the hash has child item.
     * @param int    $cut      The number of items the hash has been cut by.
     */
    protected function dumpEllipsis(Cursor $cursor, $hasChild, $cut)
    {
        if ($cut) {
            $this->line .= ' …';
            if (0 < $cut) {
                $this->line .= $cut;
            }
            if ($hasChild) {
                $this->dumpLine($cursor->depth + 1);
            }
        }
    }

    /**
     * Dumps a key in a hash structure.
     *
     * @param Cursor $cursor The Cursor position in the dump.
     */
    protected function dumpKey(Cursor $cursor)
    {
        if (null !== $key = $cursor->hashKey) {
            if ($bin = isset($key[0]) && !preg_match('//u', $key)) {
                $key = Data::utf8Encode($key);
                $bin = 'b';
            }
            switch ($cursor->hashType) {
                default:
                case Cursor::HASH_INDEXED:
                case Cursor::HASH_ASSOC:
                    if (is_int($key)) {
                        $this->line .= $this->style('meta', $key).' => ';
                    } else {
                        $this->line .= $bin.'"'.$this->style('meta', $key).'" => ';
                    }
                    break;

                case Cursor::HASH_RESOURCE:
                    $key = "\0~\0".$key;
                    // No break;
                case Cursor::HASH_OBJECT:
                    if (!isset($key[0]) || "\0" !== $key[0]) {
                        $this->line .= $bin.$this->style('public', $key).': ';
                    } elseif (0 < strpos($key, "\0", 1)) {
                        $key = explode("\0", substr($key, 1), 2);

                        switch ($key[0]) {
                            case '+': // User inserted keys
                                $this->line .= $bin.'"'.$this->style('public', $key[1]).'": ';
                                break 2;

                            case '~': $style = 'meta';      break;
                            case '*': $style = 'protected'; break;
                            default:  $style = 'private';   break;
                        }

                        $this->line .= $bin.$this->style($style, $key[1]).': ';
                    } else {
                        // This case should not happen
                        $this->line .= $bin.'"'.$this->style('private', $key).'": ';
                    }
                    break;
            }

            if ($cursor->hardRefTo) {
                $this->line .= ($cursor->hardRefCount ? $this->style('ref', '&'.$cursor->hardRefTo) : $this->style('solo-ref', '&')).' ';
            }
        }
    }

    /**
     * Decorates a value with some style.
     *
     * @param string $style The type of style being applied.
     * @param string $val   The value being styled.
     *
     * @return string The value with style decoration.
     */
    protected function style($style, $val)
    {
        if (null === $this->colors) {
            $this->colors = $this->supportsColors($this->outputStream);
        }

        if (!$this->colors || '' === $val) {
            return $val;
        }

        if ('str' === $style || 'meta' === $style || 'public' === $style) {
            foreach (static::$controlChars as $c) {
                if (false !== strpos($val, $c)) {
                    $r = "\x7F" === $c ? '?' : chr(64 + ord($c));
                    $r = "\033[{$this->styles[$style]};{$this->styles['cchr']}m{$r}\033[m";
                    $r = "\033[m{$r}\033[{$this->styles[$style]}m";
                    $val = str_replace($c, $r, $val);
                }
            }
        }

        return sprintf("\033[%sm%s\033[m", $this->styles[$style], $val);
    }

    /**
     * @return bool Tells if the current output stream supports ANSI colors or not.
     */
    protected function supportsColors()
    {
        if ($this->outputStream !== static::$defaultOutputStream) {
            return @(is_resource($this->outputStream) && function_exists('posix_isatty') && posix_isatty($this->outputStream));
        }
        if (null !== static::$defaultColors) {
            return static::$defaultColors;
        }
        if (isset($_SERVER['argv'][1])) {
            $colors = $_SERVER['argv'];
            $i = count($colors);
            while (--$i > 0) {
                if (isset($colors[$i][5])) {
                    switch ($colors[$i]) {
                        case '--ansi':
                        case '--color':
                        case '--color=yes':
                        case '--color=force':
                        case '--color=always':
                            return static::$defaultColors = true;

                        case '--no-ansi':
                        case '--color=no':
                        case '--color=none':
                        case '--color=never':
                            return static::$defaultColors = false;
                    }
                }
            }
        }

        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            static::$defaultColors = @(false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI'));
        } elseif (function_exists('posix_isatty')) {
            $h = stream_get_meta_data($this->outputStream) + array('wrapper_type' => null);
            $h = 'Output' === $h['stream_type'] && 'PHP' === $h['wrapper_type'] ? fopen('php://stdout', 'wb') : $this->outputStream;
            static::$defaultColors = @posix_isatty($h);
        } else {
            static::$defaultColors = false;
        }

        return static::$defaultColors;
    }
}
