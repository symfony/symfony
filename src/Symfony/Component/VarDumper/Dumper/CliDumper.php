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

    protected static $controlCharsRx = '/[\x00-\x1F\x7F]/';

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
    public function dumpScalar(Cursor $cursor, $type, $value)
    {
        $this->dumpKey($cursor);

        $style = 'const';
        $attr = array();

        switch ($type) {
            case 'integer':
                $style = 'num';
                break;

            case 'double':
                $style = 'num';

                switch (true) {
                    case INF === $value:  $value = 'INF';  break;
                    case -INF === $value: $value = '-INF'; break;
                    case is_nan($value):  $value = 'NAN';  break;
                    default:
                        $value = (string) $value;
                        if (false === strpos($value, $this->decimalPoint)) {
                            $value .= $this->decimalPoint.'0';
                        }
                        break;
                }
                break;

            case 'NULL':
                $value = 'null';
                break;

            case 'boolean':
                $value = $value ? 'true' : 'false';
                break;

            default:
                $attr['value'] = isset($value[0]) && !preg_match('//u', $value) ? Data::utf8Encode($value) : $value;
                $value = isset($type[0]) && !preg_match('//u', $type) ? Data::utf8Encode($type) : $type;
                break;
        }

        $this->line .= $this->style($style, $value, $attr);

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
            $attr = array(
                'length' => function_exists('iconv_strlen') && 0 <= $cut ? iconv_strlen($str, 'UTF-8') + $cut : 0,
                'binary' => $bin,
            );
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
                $this->line .= $this->style('str', $str, $attr);

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

        if ($cursor->softRefCount) {
            $prefix .= $this->style('ref', (Cursor::HASH_RESOURCE === $type ? '@' : '#').(0 < $cursor->softRefHandle ? $cursor->softRefHandle : $cursor->softRefTo), array('count' => $cursor->softRefCount));
        } elseif (0 < $cursor->softRefHandle) {
            $prefix .= $this->style('solo-ref', (Cursor::HASH_RESOURCE === $type ? '@' : '#').$cursor->softRefHandle);
        } elseif ($cursor->hardRefTo && !$cursor->refIndex && $class) {
            $prefix .= $this->style('ref', '&'.$cursor->hardRefTo, array('count' => $cursor->hardRefCount));
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
            $attr = array('binary' => $cursor->hashKeyIsBinary);
            $bin = $cursor->hashKeyIsBinary ? 'b' : '';
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
                                $attr['dynamic'] = true;
                                $this->line .= $bin.'"'.$this->style('public', $key[1], $attr).'": ';
                                break 2;

                            case '~': $style = 'meta';      break;
                            case '*': $style = 'protected'; break;
                            default:
                                $attr['class'] = $key[0];
                                $style = 'private';
                                break;
                        }

                        $this->line .= $bin.$this->style($style, $key[1], $attr).': ';
                    } else {
                        // This case should not happen
                        $this->line .= $bin.'"'.$this->style('private', $key, array('class' => '')).'": ';
                    }
                    break;
            }

            if ($cursor->hardRefTo) {
                $this->line .= ($cursor->hardRefCount ? $this->style('ref', '&'.$cursor->hardRefTo, array('count' => $cursor->hardRefCount)) : $this->style('solo-ref', '&')).' ';
            }
        }
    }

    /**
     * Decorates a value with some style.
     *
     * @param string $style The type of style being applied.
     * @param string $value The value being styled.
     * @param array  $attr  Optional context information.
     *
     * @return string The value with style decoration.
     */
    protected function style($style, $value, $attr = array())
    {
        if (null === $this->colors) {
            $this->colors = $this->supportsColors($this->outputStream);
        }

        $style = $this->styles[$style];
        $cchr = $this->colors ? "\033[m\033[{$style};{$this->styles['cchr']}m%s\033[m\033[{$style}m" : '%s';
        $value = preg_replace_callback(self::$controlCharsRx, function ($r) use ($cchr) {
            return sprintf($cchr, "\x7F" === $r[0] ? '?' : chr(64 + ord($r[0])));
        }, $value);

        return $this->colors ? sprintf("\033[%sm%s\033[m", $style, $value) : $value;
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
