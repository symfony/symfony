<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String;

use Symfony\Component\String\Exception\ExceptionInterface;
use Symfony\Component\String\Exception\InvalidArgumentException;
use Symfony\Component\String\Exception\RuntimeException;

/**
 * Represents a string of abstract Unicode characters.
 *
 * Unicode defines 3 types of "characters" (bytes, code points and grapheme clusters).
 * This class is the abstract type to use as a type-hint when the logic you want to
 * implement is Unicode-aware but doesn't care about code points vs grapheme clusters.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @throws ExceptionInterface
 */
abstract class AbstractUnicodeString extends AbstractString
{
    public const NFC = \Normalizer::NFC;
    public const NFD = \Normalizer::NFD;
    public const NFKC = \Normalizer::NFKC;
    public const NFKD = \Normalizer::NFKD;

    // all ASCII letters sorted by typical frequency of occurrence
    private const ASCII = "\x20\x65\x69\x61\x73\x6E\x74\x72\x6F\x6C\x75\x64\x5D\x5B\x63\x6D\x70\x27\x0A\x67\x7C\x68\x76\x2E\x66\x62\x2C\x3A\x3D\x2D\x71\x31\x30\x43\x32\x2A\x79\x78\x29\x28\x4C\x39\x41\x53\x2F\x50\x22\x45\x6A\x4D\x49\x6B\x33\x3E\x35\x54\x3C\x44\x34\x7D\x42\x7B\x38\x46\x77\x52\x36\x37\x55\x47\x4E\x3B\x4A\x7A\x56\x23\x48\x4F\x57\x5F\x26\x21\x4B\x3F\x58\x51\x25\x59\x5C\x09\x5A\x2B\x7E\x5E\x24\x40\x60\x7F\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";

    // the subset of folded case mappings that is not in lower case mappings
    private const FOLD = ['İ' => 'i̇', 'µ' => 'μ', 'ſ' => 's', "\xCD\x85" => 'ι', 'ς' => 'σ', 'ϐ' => 'β', 'ϑ' => 'θ', 'ϕ' => 'φ', 'ϖ' => 'π', 'ϰ' => 'κ', 'ϱ' => 'ρ', 'ϵ' => 'ε', 'ẛ' => 'ṡ', "\xE1\xBE\xBE" => 'ι', 'ß' => 'ss', 'ŉ' => 'ʼn', 'ǰ' => 'ǰ', 'ΐ' => 'ΐ', 'ΰ' => 'ΰ', 'և' => 'եւ', 'ẖ' => 'ẖ', 'ẗ' => 'ẗ', 'ẘ' => 'ẘ', 'ẙ' => 'ẙ', 'ẚ' => 'aʾ', 'ẞ' => 'ss', 'ὐ' => 'ὐ', 'ὒ' => 'ὒ', 'ὔ' => 'ὔ', 'ὖ' => 'ὖ', 'ᾀ' => 'ἀι', 'ᾁ' => 'ἁι', 'ᾂ' => 'ἂι', 'ᾃ' => 'ἃι', 'ᾄ' => 'ἄι', 'ᾅ' => 'ἅι', 'ᾆ' => 'ἆι', 'ᾇ' => 'ἇι', 'ᾈ' => 'ἀι', 'ᾉ' => 'ἁι', 'ᾊ' => 'ἂι', 'ᾋ' => 'ἃι', 'ᾌ' => 'ἄι', 'ᾍ' => 'ἅι', 'ᾎ' => 'ἆι', 'ᾏ' => 'ἇι', 'ᾐ' => 'ἠι', 'ᾑ' => 'ἡι', 'ᾒ' => 'ἢι', 'ᾓ' => 'ἣι', 'ᾔ' => 'ἤι', 'ᾕ' => 'ἥι', 'ᾖ' => 'ἦι', 'ᾗ' => 'ἧι', 'ᾘ' => 'ἠι', 'ᾙ' => 'ἡι', 'ᾚ' => 'ἢι', 'ᾛ' => 'ἣι', 'ᾜ' => 'ἤι', 'ᾝ' => 'ἥι', 'ᾞ' => 'ἦι', 'ᾟ' => 'ἧι', 'ᾠ' => 'ὠι', 'ᾡ' => 'ὡι', 'ᾢ' => 'ὢι', 'ᾣ' => 'ὣι', 'ᾤ' => 'ὤι', 'ᾥ' => 'ὥι', 'ᾦ' => 'ὦι', 'ᾧ' => 'ὧι', 'ᾨ' => 'ὠι', 'ᾩ' => 'ὡι', 'ᾪ' => 'ὢι', 'ᾫ' => 'ὣι', 'ᾬ' => 'ὤι', 'ᾭ' => 'ὥι', 'ᾮ' => 'ὦι', 'ᾯ' => 'ὧι', 'ᾲ' => 'ὰι', 'ᾳ' => 'αι', 'ᾴ' => 'άι', 'ᾶ' => 'ᾶ', 'ᾷ' => 'ᾶι', 'ᾼ' => 'αι', 'ῂ' => 'ὴι', 'ῃ' => 'ηι', 'ῄ' => 'ήι', 'ῆ' => 'ῆ', 'ῇ' => 'ῆι', 'ῌ' => 'ηι', 'ῒ' => 'ῒ', 'ῖ' => 'ῖ', 'ῗ' => 'ῗ', 'ῢ' => 'ῢ', 'ῤ' => 'ῤ', 'ῦ' => 'ῦ', 'ῧ' => 'ῧ', 'ῲ' => 'ὼι', 'ῳ' => 'ωι', 'ῴ' => 'ώι', 'ῶ' => 'ῶ', 'ῷ' => 'ῶι', 'ῼ' => 'ωι', 'ﬀ' => 'ff', 'ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬃ' => 'ffi', 'ﬄ' => 'ffl', 'ﬅ' => 'st', 'ﬆ' => 'st', 'ﬓ' => 'մն', 'ﬔ' => 'մե', 'ﬕ' => 'մի', 'ﬖ' => 'վն', 'ﬗ' => 'մխ'];

    // the subset of https://github.com/unicode-org/cldr/blob/master/common/transforms/Latin-ASCII.xml that is not in NFKD
    private const TRANSLIT = ['Æ' => 'AE', 'Ð' => 'D', 'Ø' => 'O', 'Þ' => 'TH', 'ß' => 'ss', 'æ' => 'ae', 'ð' => 'd', 'ø' => 'o', 'þ' => 'th', 'Đ' => 'D', 'đ' => 'd', 'Ħ' => 'H', 'ħ' => 'h', 'ı' => 'i', 'ĸ' => 'q', 'Ŀ' => 'L', 'ŀ' => 'l', 'Ł' => 'L', 'ł' => 'l', 'ŉ' => '\'n', 'Ŋ' => 'N', 'ŋ' => 'n', 'Œ' => 'OE', 'œ' => 'oe', 'Ŧ' => 'T', 'ŧ' => 't', 'ƀ' => 'b', 'Ɓ' => 'B', 'Ƃ' => 'B', 'ƃ' => 'b', 'Ƈ' => 'C', 'ƈ' => 'c', 'Ɖ' => 'D', 'Ɗ' => 'D', 'Ƌ' => 'D', 'ƌ' => 'd', 'Ɛ' => 'E', 'Ƒ' => 'F', 'ƒ' => 'f', 'Ɠ' => 'G', 'ƕ' => 'hv', 'Ɩ' => 'I', 'Ɨ' => 'I', 'Ƙ' => 'K', 'ƙ' => 'k', 'ƚ' => 'l', 'Ɲ' => 'N', 'ƞ' => 'n', 'Ƣ' => 'OI', 'ƣ' => 'oi', 'Ƥ' => 'P', 'ƥ' => 'p', 'ƫ' => 't', 'Ƭ' => 'T', 'ƭ' => 't', 'Ʈ' => 'T', 'Ʋ' => 'V', 'Ƴ' => 'Y', 'ƴ' => 'y', 'Ƶ' => 'Z', 'ƶ' => 'z', 'Ǆ' => 'DZ', 'ǅ' => 'Dz', 'ǆ' => 'dz', 'Ǥ' => 'G', 'ǥ' => 'g', 'ȡ' => 'd', 'Ȥ' => 'Z', 'ȥ' => 'z', 'ȴ' => 'l', 'ȵ' => 'n', 'ȶ' => 't', 'ȷ' => 'j', 'ȸ' => 'db', 'ȹ' => 'qp', 'Ⱥ' => 'A', 'Ȼ' => 'C', 'ȼ' => 'c', 'Ƚ' => 'L', 'Ⱦ' => 'T', 'ȿ' => 's', 'ɀ' => 'z', 'Ƀ' => 'B', 'Ʉ' => 'U', 'Ɇ' => 'E', 'ɇ' => 'e', 'Ɉ' => 'J', 'ɉ' => 'j', 'Ɍ' => 'R', 'ɍ' => 'r', 'Ɏ' => 'Y', 'ɏ' => 'y', 'ɓ' => 'b', 'ɕ' => 'c', 'ɖ' => 'd', 'ɗ' => 'd', 'ɛ' => 'e', 'ɟ' => 'j', 'ɠ' => 'g', 'ɡ' => 'g', 'ɢ' => 'G', 'ɦ' => 'h', 'ɧ' => 'h', 'ɨ' => 'i', 'ɪ' => 'I', 'ɫ' => 'l', 'ɬ' => 'l', 'ɭ' => 'l', 'ɱ' => 'm', 'ɲ' => 'n', 'ɳ' => 'n', 'ɴ' => 'N', 'ɶ' => 'OE', 'ɼ' => 'r', 'ɽ' => 'r', 'ɾ' => 'r', 'ʀ' => 'R', 'ʂ' => 's', 'ʈ' => 't', 'ʉ' => 'u', 'ʋ' => 'v', 'ʏ' => 'Y', 'ʐ' => 'z', 'ʑ' => 'z', 'ʙ' => 'B', 'ʛ' => 'G', 'ʜ' => 'H', 'ʝ' => 'j', 'ʟ' => 'L', 'ʠ' => 'q', 'ʣ' => 'dz', 'ʥ' => 'dz', 'ʦ' => 'ts', 'ʪ' => 'ls', 'ʫ' => 'lz', 'ᴀ' => 'A', 'ᴁ' => 'AE', 'ᴃ' => 'B', 'ᴄ' => 'C', 'ᴅ' => 'D', 'ᴆ' => 'D', 'ᴇ' => 'E', 'ᴊ' => 'J', 'ᴋ' => 'K', 'ᴌ' => 'L', 'ᴍ' => 'M', 'ᴏ' => 'O', 'ᴘ' => 'P', 'ᴛ' => 'T', 'ᴜ' => 'U', 'ᴠ' => 'V', 'ᴡ' => 'W', 'ᴢ' => 'Z', 'ᵫ' => 'ue', 'ᵬ' => 'b', 'ᵭ' => 'd', 'ᵮ' => 'f', 'ᵯ' => 'm', 'ᵰ' => 'n', 'ᵱ' => 'p', 'ᵲ' => 'r', 'ᵳ' => 'r', 'ᵴ' => 's', 'ᵵ' => 't', 'ᵶ' => 'z', 'ᵺ' => 'th', 'ᵻ' => 'I', 'ᵽ' => 'p', 'ᵾ' => 'U', 'ᶀ' => 'b', 'ᶁ' => 'd', 'ᶂ' => 'f', 'ᶃ' => 'g', 'ᶄ' => 'k', 'ᶅ' => 'l', 'ᶆ' => 'm', 'ᶇ' => 'n', 'ᶈ' => 'p', 'ᶉ' => 'r', 'ᶊ' => 's', 'ᶌ' => 'v', 'ᶍ' => 'x', 'ᶎ' => 'z', 'ᶏ' => 'a', 'ᶑ' => 'd', 'ᶒ' => 'e', 'ᶓ' => 'e', 'ᶖ' => 'i', 'ᶙ' => 'u', 'ẚ' => 'a', 'ẜ' => 's', 'ẝ' => 's', 'ẞ' => 'SS', 'Ỻ' => 'LL', 'ỻ' => 'll', 'Ỽ' => 'V', 'ỽ' => 'v', 'Ỿ' => 'Y', 'ỿ' => 'y', '©' => '(C)', '®' => '(R)', '₠' => 'CE', '₢' => 'Cr', '₣' => 'Fr.', '₤' => 'L.', '₧' => 'Pts', '₺' => 'TL', '₹' => 'Rs', 'ℌ' => 'x', '℞' => 'Rx', '㎧' => 'm/s', '㎮' => 'rad/s', '㏆' => 'C/kg', '㏗' => 'pH', '㏞' => 'V/m', '㏟' => 'A/m', '¼' => ' 1/4', '½' => ' 1/2', '¾' => ' 3/4', '⅓' => ' 1/3', '⅔' => ' 2/3', '⅕' => ' 1/5', '⅖' => ' 2/5', '⅗' => ' 3/5', '⅘' => ' 4/5', '⅙' => ' 1/6', '⅚' => ' 5/6', '⅛' => ' 1/8', '⅜' => ' 3/8', '⅝' => ' 5/8', '⅞' => ' 7/8', '⅟' => ' 1/', '〇' => '0', '‘' => '\'', '’' => '\'', '‚' => ',', '‛' => '\'', '“' => '"', '”' => '"', '„' => ',,', '‟' => '"', '′' => '\'', '″' => '"', '〝' => '"', '〞' => '"', '«' => '<<', '»' => '>>', '‹' => '<', '›' => '>', '‐' => '-', '‑' => '-', '‒' => '-', '–' => '-', '—' => '-', '―' => '-', '︱' => '-', '︲' => '-', '﹘' => '-', '‖' => '||', '⁄' => '/', '⁅' => '[', '⁆' => ']', '⁎' => '*', '、' => ',', '。' => '.', '〈' => '<', '〉' => '>', '《' => '<<', '》' => '>>', '〔' => '[', '〕' => ']', '〘' => '[', '〙' => ']', '〚' => '[', '〛' => ']', '︑' => ',', '︒' => '.', '︹' => '[', '︺' => ']', '︽' => '<<', '︾' => '>>', '︿' => '<', '﹀' => '>', '﹑' => ',', '﹝' => '[', '﹞' => ']', '｟' => '((', '｠' => '))', '｡' => '.', '､' => ',', '×' => '*', '÷' => '/', '−' => '-', '∕' => '/', '∖' => '\\', '∣' => '|', '∥' => '||', '≪' => '<<', '≫' => '>>', '⦅' => '((', '⦆' => '))'];

    // \p{ExtPict} requires PCRE2 10.40; U+1F1E6-1F1FF are regional indicators, #*0-9 keycap bases
    private const EMOJI_REGEX = \PCRE_VERSION_MAJOR < 10 || \PCRE_VERSION_MAJOR === 10 && \PCRE_VERSION_MINOR < 40 ? '/[\p{So}#*0-9]/u' : '/[\p{ExtPict}\x{1F1E6}-\x{1F1FF}#*0-9]/u';

    private static array $transliterators = [];
    private static array $tableZero;
    private static array $tableWide;

    public static function fromCodePoints(int ...$codes): static
    {
        $string = '';

        foreach ($codes as $code) {
            if (0x80 > $code %= 0x200000) {
                $string .= \chr($code);
            } elseif (0x800 > $code) {
                $string .= \chr(0xC0 | $code >> 6).\chr(0x80 | $code & 0x3F);
            } elseif (0x10000 > $code) {
                $string .= \chr(0xE0 | $code >> 12).\chr(0x80 | $code >> 6 & 0x3F).\chr(0x80 | $code & 0x3F);
            } else {
                $string .= \chr(0xF0 | $code >> 18).\chr(0x80 | $code >> 12 & 0x3F).\chr(0x80 | $code >> 6 & 0x3F).\chr(0x80 | $code & 0x3F);
            }
        }

        return new static($string);
    }

    /**
     * Generic UTF-8 to ASCII transliteration.
     *
     * Install the intl extension for best results.
     *
     * @param string[]|\Transliterator[]|\Closure[] $rules See "*-Latin" rules from Transliterator::listIDs()
     */
    public function ascii(array $rules = []): self
    {
        $str = clone $this;
        $s = $str->string;
        $str->string = '';

        array_unshift($rules, 'nfd');
        $rules[] = 'latin-ascii';

        if (\function_exists('transliterator_transliterate')) {
            $rules[] = 'any-latin/bgn';
        }

        $rules[] = 'nfkd';
        $rules[] = '[:nonspacing mark:] remove';

        while (\strlen($s) - 1 > $i = strspn($s, self::ASCII)) {
            if (0 < --$i) {
                $str->string .= substr($s, 0, $i);
                $s = substr($s, $i);
            }

            if (!$rule = array_shift($rules)) {
                $rules = []; // An empty rule interrupts the next ones
            }

            if ($rule instanceof \Transliterator) {
                $s = $rule->transliterate($s);
            } elseif ($rule instanceof \Closure) {
                $s = $rule($s);
            } elseif ($rule) {
                if ('nfd' === $rule = strtolower($rule)) {
                    normalizer_is_normalized($s, self::NFD) ?: $s = normalizer_normalize($s, self::NFD);
                } elseif ('nfkd' === $rule) {
                    normalizer_is_normalized($s, self::NFKD) ?: $s = normalizer_normalize($s, self::NFKD);
                } elseif ('[:nonspacing mark:] remove' === $rule) {
                    $s = preg_replace('/\p{Mn}++/u', '', $s);
                } elseif ('latin-ascii' === $rule) {
                    $s = strtr($s, self::TRANSLIT);
                } elseif ('de-ascii' === $rule) {
                    $s = preg_replace("/([AUO])\u{0308}(?=\p{Ll})/u", '$1e', $s);
                    $s = str_replace(["a\u{0308}", "o\u{0308}", "u\u{0308}", "A\u{0308}", "O\u{0308}", "U\u{0308}"], ['ae', 'oe', 'ue', 'AE', 'OE', 'UE'], $s);
                } elseif (\function_exists('transliterator_transliterate')) {
                    if (null === $transliterator = self::$transliterators[$rule] ??= \Transliterator::create($rule)) {
                        if ('any-latin/bgn' === $rule) {
                            $rule = 'any-latin';
                            $transliterator = self::$transliterators[$rule] ??= \Transliterator::create($rule);
                        }

                        if (null === $transliterator) {
                            throw new InvalidArgumentException(\sprintf('Unknown transliteration rule "%s".', $rule));
                        }

                        self::$transliterators['any-latin/bgn'] = $transliterator;
                    }

                    $s = $transliterator->transliterate($s);
                }
            } elseif (!\function_exists('iconv')) {
                $s = preg_replace('/[^\x00-\x7F]/u', '?', $s);
            } else {
                $previousLocale = setlocale(\LC_CTYPE, 0);
                try {
                    setlocale(\LC_CTYPE, 'C');
                    $s = @preg_replace_callback('/[^\x00-\x7F]/u', static function ($c) {
                        $c = (string) iconv('UTF-8', 'ASCII//TRANSLIT', $c[0]);

                        if ('' === $c && '' === iconv('UTF-8', 'ASCII//TRANSLIT', '²')) {
                            throw new \LogicException(\sprintf('"%s" requires a translit-able iconv implementation, try installing "gnu-libiconv" if you\'re using Alpine Linux.', static::class));
                        }

                        return 1 < \strlen($c) ? ltrim($c, '\'`"^~') : ('' !== $c ? $c : '?');
                    }, $s);
                } finally {
                    setlocale(\LC_CTYPE, $previousLocale);
                }
            }
        }

        $str->string .= $s;

        return $str;
    }

    public function camel(): static
    {
        $str = clone $this;
        $str->string = str_replace(' ', '', preg_replace_callback('/\b.(?!\p{Lu})/u', static function ($m) {
            static $i = 0;

            return 1 === ++$i ? ('İ' === $m[0] ? 'i̇' : mb_strtolower($m[0], 'UTF-8')) : mb_convert_case($m[0], \MB_CASE_TITLE, 'UTF-8');
        }, preg_replace('/[^\pL0-9]++/u', ' ', $this->string)));

        return $str;
    }

    /**
     * @return int[]
     */
    public function codePointsAt(int $offset): array
    {
        $str = $this->slice($offset, 1);

        if ('' === $str->string) {
            return [];
        }

        $codePoints = [];

        foreach (preg_split('//u', $str->string, -1, \PREG_SPLIT_NO_EMPTY) as $c) {
            $codePoints[] = mb_ord($c, 'UTF-8');
        }

        return $codePoints;
    }

    public function folded(bool $compat = true): static
    {
        $str = clone $this;

        if (!$compat || !\defined('Normalizer::NFKC_CF')) {
            $str->string = normalizer_normalize($str->string, $compat ? \Normalizer::NFKC : \Normalizer::NFC);
            $str->string = mb_strtolower(strtr($str->string, self::FOLD), 'UTF-8');
        } else {
            $str->string = normalizer_normalize($str->string, \Normalizer::NFKC_CF);
        }

        return $str;
    }

    public function join(array $strings, ?string $lastGlue = null): static
    {
        $str = clone $this;

        $tail = null !== $lastGlue && 1 < \count($strings) ? $lastGlue.array_pop($strings) : '';
        $str->string = implode($this->string, $strings).$tail;

        if (!preg_match('//u', $str->string)) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        }

        return $str;
    }

    /**
     * @param string|null $regexp A pattern matching the parts to convert, or null to convert the whole string
     */
    public function lower(/* ?string $regexp = null */): static
    {
        $regexp = 1 <= \func_num_args() ? func_get_arg(0) : null;

        if (null !== $regexp) {
            return $this->replaceMatches($regexp, static fn (array $m): string => mb_strtolower(str_replace('İ', 'i̇', $m[0]), 'UTF-8'));
        }

        $str = clone $this;
        $str->string = mb_strtolower(str_replace('İ', 'i̇', $str->string), 'UTF-8');

        return $str;
    }

    /**
     * @param string      $locale In the format language_region (e.g. tr_TR)
     * @param string|null $regexp A pattern matching the parts to convert, or null to convert the whole string
     */
    public function localeLower(string $locale /* , ?string $regexp = null */): static
    {
        $regexp = 2 <= \func_num_args() ? func_get_arg(1) : null;

        if (null !== $transliterator = $this->getLocaleTransliterator($locale, 'Lower')) {
            if (null !== $regexp) {
                return $this->replaceMatches($regexp, static fn (array $m): string => $transliterator->transliterate($m[0]));
            }

            $str = clone $this;
            $str->string = $transliterator->transliterate($str->string);

            return $str;
        }

        return $this->lower($regexp);
    }

    public function match(string $regexp, int $flags = 0, int $offset = 0): array
    {
        $match = ((\PREG_PATTERN_ORDER | \PREG_SET_ORDER) & $flags) ? 'preg_match_all' : 'preg_match';

        if ($this->ignoreCase) {
            $regexp .= 'i';
        }

        set_error_handler(static fn ($t, $m) => throw new InvalidArgumentException($m));

        try {
            if (false === $match($regexp.'u', $this->string, $matches, $flags | \PREG_UNMATCHED_AS_NULL, $offset)) {
                throw new RuntimeException('Matching failed with error: '.preg_last_error_msg());
            }
        } finally {
            restore_error_handler();
        }

        return $matches;
    }

    public function normalize(int $form = self::NFC): static
    {
        if (!\in_array($form, [self::NFC, self::NFD, self::NFKC, self::NFKD], true)) {
            throw new InvalidArgumentException('Unsupported normalization form.');
        }

        $str = clone $this;
        normalizer_is_normalized($str->string, $form) ?: $str->string = normalizer_normalize($str->string, $form);

        return $str;
    }

    public function padBoth(int $length, string $padStr = ' '): static
    {
        if ('' === $padStr || !preg_match('//u', $padStr)) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        }

        $pad = clone $this;
        $pad->string = $padStr;

        return $this->pad($length, $pad, \STR_PAD_BOTH);
    }

    public function padEnd(int $length, string $padStr = ' '): static
    {
        if ('' === $padStr || !preg_match('//u', $padStr)) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        }

        $pad = clone $this;
        $pad->string = $padStr;

        return $this->pad($length, $pad, \STR_PAD_RIGHT);
    }

    public function padStart(int $length, string $padStr = ' '): static
    {
        if ('' === $padStr || !preg_match('//u', $padStr)) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        }

        $pad = clone $this;
        $pad->string = $padStr;

        return $this->pad($length, $pad, \STR_PAD_LEFT);
    }

    /**
     * @param-immediately-invoked-callable $to
     */
    public function replaceMatches(string $fromRegexp, string|callable $to): static
    {
        if ($this->ignoreCase) {
            $fromRegexp .= 'i';
        }

        if (\is_array($to) || $to instanceof \Closure) {
            $replace = 'preg_replace_callback';
            $to = static function (array $m) use ($to): string {
                $to = $to($m);

                if ('' !== $to && (!\is_string($to) || !preg_match('//u', $to))) {
                    throw new InvalidArgumentException('Replace callback must return a valid UTF-8 string.');
                }

                return $to;
            };
        } elseif ('' !== $to && !preg_match('//u', $to)) {
            throw new InvalidArgumentException('Invalid UTF-8 string.');
        } else {
            $replace = 'preg_replace';
        }

        set_error_handler(static fn ($t, $m) => throw new InvalidArgumentException($m));

        try {
            if (null === $string = $replace($fromRegexp.'u', $to, $this->string)) {
                $lastError = preg_last_error();

                foreach (get_defined_constants(true)['pcre'] as $k => $v) {
                    if ($lastError === $v && str_ends_with($k, '_ERROR')) {
                        throw new RuntimeException('Matching failed with '.$k.'.');
                    }
                }

                throw new RuntimeException('Matching failed with unknown error code.');
            }
        } finally {
            restore_error_handler();
        }

        $str = clone $this;
        $str->string = $string;

        return $str;
    }

    public function reverse(): static
    {
        $str = clone $this;
        $str->string = implode('', array_reverse(grapheme_str_split($str->string)));

        return $str;
    }

    public function snake(): static
    {
        $str = $this->camel();
        $str->string = mb_strtolower(preg_replace(['/(\p{Lu}+)(\p{Lu}\p{Ll})/u', '/([\p{Ll}0-9])(\p{Lu})/u'], '\1_\2', $str->string), 'UTF-8');

        return $str;
    }

    /**
     * @param string|null $regexp A pattern matching the parts to convert, or null to convert the whole string;
     *                            when set, every match is converted and $allWords is ignored
     */
    public function title(bool $allWords = false /* , ?string $regexp = null */): static
    {
        $regexp = 2 <= \func_num_args() ? func_get_arg(1) : null;

        if (null !== $regexp) {
            return $this->replaceMatches($regexp, static fn (array $m): string => mb_convert_case($m[0], \MB_CASE_TITLE, 'UTF-8'));
        }

        $str = clone $this;

        $limit = $allWords ? -1 : 1;

        $str->string = preg_replace_callback('/\b./u', static fn (array $m): string => mb_convert_case($m[0], \MB_CASE_TITLE, 'UTF-8'), $str->string, $limit);

        return $str;
    }

    /**
     * @param string      $locale In the format language_region (e.g. tr_TR)
     * @param string|null $regexp A pattern matching the parts to convert, or null to convert the whole string
     */
    public function localeTitle(string $locale /* , ?string $regexp = null */): static
    {
        $regexp = 2 <= \func_num_args() ? func_get_arg(1) : null;

        if (null !== $transliterator = $this->getLocaleTransliterator($locale, 'Title')) {
            if (null !== $regexp) {
                return $this->replaceMatches($regexp, static fn (array $m): string => $transliterator->transliterate($m[0]));
            }

            $str = clone $this;
            $str->string = $transliterator->transliterate($str->string);

            return $str;
        }

        if (null !== $regexp) {
            return $this->title(false, $regexp);
        }

        $str = clone $this;
        $str->string = mb_convert_case($str->string, \MB_CASE_TITLE, 'UTF-8');

        return $str;
    }

    public function trim(string $chars = " \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}"): static
    {
        if (" \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}" !== $chars && !preg_match('//u', $chars)) {
            throw new InvalidArgumentException('Invalid UTF-8 chars.');
        }
        $chars = preg_quote($chars);

        $str = clone $this;
        $str->string = preg_replace("{^[$chars]++|[$chars]++$}uD", '', $str->string);

        return $str;
    }

    public function trimEnd(string $chars = " \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}"): static
    {
        if (" \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}" !== $chars && !preg_match('//u', $chars)) {
            throw new InvalidArgumentException('Invalid UTF-8 chars.');
        }
        $chars = preg_quote($chars);

        $str = clone $this;
        $str->string = preg_replace("{[$chars]++$}uD", '', $str->string);

        return $str;
    }

    public function trimPrefix($prefix): static
    {
        if (!$this->ignoreCase) {
            return parent::trimPrefix($prefix);
        }

        $str = clone $this;

        if ($prefix instanceof \Traversable) {
            $prefix = iterator_to_array($prefix, false);
        } elseif ($prefix instanceof parent) {
            $prefix = $prefix->string;
        }

        $prefix = implode('|', array_map('preg_quote', (array) $prefix));
        $str->string = preg_replace("{^(?:$prefix)}iuD", '', $this->string);

        return $str;
    }

    public function trimStart(string $chars = " \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}"): static
    {
        if (" \t\n\r\0\x0B\x0C\u{A0}\u{FEFF}" !== $chars && !preg_match('//u', $chars)) {
            throw new InvalidArgumentException('Invalid UTF-8 chars.');
        }
        $chars = preg_quote($chars);

        $str = clone $this;
        $str->string = preg_replace("{^[$chars]++}uD", '', $str->string);

        return $str;
    }

    public function trimSuffix($suffix): static
    {
        if (!$this->ignoreCase) {
            return parent::trimSuffix($suffix);
        }

        $str = clone $this;

        if ($suffix instanceof \Traversable) {
            $suffix = iterator_to_array($suffix, false);
        } elseif ($suffix instanceof parent) {
            $suffix = $suffix->string;
        }

        $suffix = implode('|', array_map('preg_quote', (array) $suffix));
        $str->string = preg_replace("{(?:$suffix)$}iuD", '', $this->string);

        return $str;
    }

    /**
     * @param string|null $regexp A pattern matching the parts to convert, or null to convert the whole string
     */
    public function upper(/* ?string $regexp = null */): static
    {
        $regexp = 1 <= \func_num_args() ? func_get_arg(0) : null;

        if (null !== $regexp) {
            return $this->replaceMatches($regexp, static fn (array $m): string => mb_strtoupper($m[0], 'UTF-8'));
        }

        $str = clone $this;
        $str->string = mb_strtoupper($str->string, 'UTF-8');

        return $str;
    }

    /**
     * @param string      $locale In the format language_region (e.g. tr_TR)
     * @param string|null $regexp A pattern matching the parts to convert, or null to convert the whole string
     */
    public function localeUpper(string $locale /* , ?string $regexp = null */): static
    {
        $regexp = 2 <= \func_num_args() ? func_get_arg(1) : null;

        if (null !== $transliterator = $this->getLocaleTransliterator($locale, 'Upper')) {
            if (null !== $regexp) {
                return $this->replaceMatches($regexp, static fn (array $m): string => $transliterator->transliterate($m[0]));
            }

            $str = clone $this;
            $str->string = $transliterator->transliterate($str->string);

            return $str;
        }

        return $this->upper($regexp);
    }

    public function width(bool $ignoreAnsiDecoration = true): int
    {
        if (!preg_match('/[^\x20-\x7E]/', $this->string)) {
            return \strlen($this->string);
        }

        $width = 0;
        $s = $this->string;

        if ($ignoreAnsiDecoration && str_contains($s, "\x07")) {
            // BEL can terminate an OSC sequence instead of ST: strip such sequences before BEL is removed
            $s = preg_replace('/\x1B\][^\x07\x1B\n\r]*+\x07/', '', $s);
        }

        $s = str_replace(["\x00", "\x05", "\x07"], '', $s);

        if (str_contains($s, "\r")) {
            $s = str_replace(["\r\n", "\r"], "\n", $s);
        }

        if (!$ignoreAnsiDecoration) {
            $s = preg_replace('/[\p{Cc}\x7F]++/u', '', $s);
        }

        foreach (explode("\n", $s) as $s) {
            if ($ignoreAnsiDecoration) {
                $s = preg_replace('/(?:\x1B(?:
                    \[ [\x30-\x3F]*+ [\x20-\x2F]*+ [\x40-\x7E]
                    | [P\]X^_] .*? \x1B\\\\
                    | [\x20-\x2F]*+ [\x30-\x7E]?
                )|[\p{Cc}\x7F]++)/xu', '', $s);
            }

            $lineWidth = $this->wcswidth($s);

            if ($lineWidth > $width) {
                $width = $lineWidth;
            }
        }

        return $width;
    }

    private function pad(int $len, self $pad, int $type): static
    {
        $sLen = $this->length();

        if ($len <= $sLen) {
            return clone $this;
        }

        $padLen = $pad->length();
        $freeLen = $len - $sLen;
        $len = $freeLen % $padLen;

        switch ($type) {
            case \STR_PAD_RIGHT:
                return $this->append(str_repeat($pad->string, intdiv($freeLen, $padLen)).($len ? $pad->slice(0, $len) : ''));

            case \STR_PAD_LEFT:
                return $this->prepend(str_repeat($pad->string, intdiv($freeLen, $padLen)).($len ? $pad->slice(0, $len) : ''));

            case \STR_PAD_BOTH:
                $freeLen /= 2;

                $rightLen = ceil($freeLen);
                $len = $rightLen % $padLen;
                $str = $this->append(str_repeat($pad->string, intdiv($rightLen, $padLen)).($len ? $pad->slice(0, $len) : ''));

                $leftLen = floor($freeLen);
                $len = $leftLen % $padLen;

                return $str->prepend(str_repeat($pad->string, intdiv($leftLen, $padLen)).($len ? $pad->slice(0, $len) : ''));

            default:
                throw new InvalidArgumentException('Invalid padding type.');
        }
    }

    /**
     * Based on https://github.com/jquast/wcwidth, a Python implementation of https://www.cl.cam.ac.uk/~mgk25/ucs/wcwidth.c.
     */
    private function wcswidth(string $string): int
    {
        if (!preg_match('/[^\x20-\x7E]/', $string)) {
            return \strlen($string);
        }

        $width = 0;
        $lastChar = null;
        $lastWidth = null;
        $joined = false;

        foreach (preg_split('//u', $string, -1, \PREG_SPLIT_NO_EMPTY) as $c) {
            if ($joined) {
                $joined = false;

                continue;
            }

            $codePoint = mb_ord($c, 'UTF-8');

            if (0x7F > $codePoint && 0x20 <= $codePoint) { // printable ASCII
                ++$width;
                $lastChar = $c;
                $lastWidth = 1;

                continue;
            }

            if (0 === $codePoint // NULL
                || 0x034F === $codePoint // COMBINING GRAPHEME JOINER
                || 0x061C === $codePoint // ARABIC LETTER MARK
                || (0x1160 <= $codePoint && 0x11FF >= $codePoint) // HANGUL JUNGSEONG FILLER to HANGUL JONGSEONG SSANGNIEUN
                || 0x180E === $codePoint // MONGOLIAN VOWEL SEPARATOR
                || (0x200B <= $codePoint && 0x200F >= $codePoint) // ZERO WIDTH SPACE to RIGHT-TO-LEFT MARK
                || 0x2028 === $codePoint // LINE SEPARATOR
                || 0x2029 === $codePoint // PARAGRAPH SEPARATOR
                || (0x202A <= $codePoint && 0x202E >= $codePoint) // LEFT-TO-RIGHT EMBEDDING to RIGHT-TO-LEFT OVERRIDE
                || (0x2060 <= $codePoint && 0x206F >= $codePoint) // WORD JOINER to NOMINAL DIGIT SHAPES
                || (0xD7B0 <= $codePoint && 0xD7FF >= $codePoint) // HANGUL JAMO EXTENDED-B
                || 0xFEFF === $codePoint // ZERO WIDTH NO-BREAK SPACE
                || (0xFFF0 <= $codePoint && 0xFFFB >= $codePoint) // INTERLINEAR ANNOTATION ANCHOR to TERMINATOR, and the unassigned code points before them
            ) {
                // after an emoji, a ZERO WIDTH JOINER makes the next character part of it
                $joined = 0x200D === $codePoint && null !== $lastChar && 1 < \strlen($lastChar) && preg_match(self::EMOJI_REGEX, $lastChar);

                continue;
            }

            if (0x0A0 > $codePoint) { // C0 and C1 control characters and DEL
                return -1;
            }

            if (0xFE0F === $codePoint) {
                if (null !== $lastChar && 1 === $lastWidth && preg_match(self::EMOJI_REGEX, $lastChar)) {
                    ++$width;
                    $lastWidth = 2;
                }

                continue;
            }

            if (0xFFFF < $codePoint) {
                if ((0x13430 <= $codePoint && 0x1343F >= $codePoint) // EGYPTIAN HIEROGLYPH VERTICAL JOINER to EGYPTIAN HIEROGLYPH END WALLED ENCLOSURE
                    || (0x1BCA0 <= $codePoint && 0x1BCA3 >= $codePoint) // SHORTHAND FORMAT LETTER OVERLAP to SHORTHAND FORMAT UP STEP
                    || (0x1D173 <= $codePoint && 0x1D17A >= $codePoint) // MUSICAL SYMBOL BEGIN BEAM to MUSICAL SYMBOL END PHRASE
                    || (0xE0000 <= $codePoint && 0xE0FFF >= $codePoint) // TAGS and the default ignorable code points around them
                ) {
                    continue;
                }

                if (0x1F3FB <= $codePoint && 0x1F3FF >= $codePoint && null !== $lastChar && 1 < \strlen($lastChar) && preg_match(self::EMOJI_REGEX, $lastChar)) { // EMOJI MODIFIER FITZPATRICK TYPE-1-2 to TYPE-6, not after a keycap base
                    // like VS16, a skin tone gives a text-default emoji its emoji presentation
                    if (1 === $lastWidth) {
                        ++$width;
                        $lastWidth = 2;
                    }

                    continue;
                }
            }

            self::$tableZero ??= require __DIR__.'/Resources/data/wcswidth_table_zero.php';

            if ($codePoint >= self::$tableZero[0][0] && $codePoint <= self::$tableZero[$ubound = \count(self::$tableZero) - 1][1]) {
                $lbound = 0;
                while ($ubound >= $lbound) {
                    $mid = ($lbound + $ubound) >> 1;

                    if ($codePoint > self::$tableZero[$mid][1]) {
                        $lbound = $mid + 1;
                    } elseif ($codePoint < self::$tableZero[$mid][0]) {
                        $ubound = $mid - 1;
                    } else {
                        continue 2;
                    }
                }
            }

            self::$tableWide ??= require __DIR__.'/Resources/data/wcswidth_table_wide.php';

            if ($codePoint >= self::$tableWide[0][0] && $codePoint <= self::$tableWide[$ubound = \count(self::$tableWide) - 1][1]) {
                $lbound = 0;
                while ($ubound >= $lbound) {
                    $mid = ($lbound + $ubound) >> 1;

                    if ($codePoint > self::$tableWide[$mid][1]) {
                        $lbound = $mid + 1;
                    } elseif ($codePoint < self::$tableWide[$mid][0]) {
                        $ubound = $mid - 1;
                    } else {
                        $width += 2;
                        $lastChar = $c;
                        $lastWidth = 2;

                        continue 2;
                    }
                }
            }

            ++$width;
            $lastChar = $c;
            $lastWidth = 1;
        }

        return $width;
    }

    private function getLocaleTransliterator(string $locale, string $id): ?\Transliterator
    {
        $rule = $locale.'-'.$id;
        if (\array_key_exists($rule, self::$transliterators)) {
            return self::$transliterators[$rule];
        }

        if (null !== $transliterator = self::$transliterators[$rule] = \Transliterator::create($rule)) {
            return $transliterator;
        }

        // Try to find a parent locale (nl_BE -> nl)
        if (false === $i = strpos($locale, '_')) {
            return null;
        }

        $parentRule = substr_replace($locale, '-'.$id, $i);

        // Parent locale was already cached, return and store as current locale
        if (\array_key_exists($parentRule, self::$transliterators)) {
            return self::$transliterators[$rule] = self::$transliterators[$parentRule];
        }

        // Create transliterator based on parent locale and cache the result on both initial and parent locale values
        $transliterator = \Transliterator::create($parentRule);

        return self::$transliterators[$rule] = self::$transliterators[$parentRule] = $transliterator;
    }
}
