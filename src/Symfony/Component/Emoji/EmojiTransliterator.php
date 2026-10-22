<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Emoji;

use Symfony\Component\Emoji\Util\GzipStreamWrapper;

if (!class_exists(\Transliterator::class)) {
    throw new \LogicException(\sprintf('You cannot use the "%s\EmojiTransliterator" class as the "intl" extension is not installed. See https://php.net/intl.', __NAMESPACE__));
}

final class EmojiTransliterator extends \Transliterator
{
    private const QUICK_CHECK = "\xA9\xAE\xE2\xE3\xF0";
    private const MAX_KEY_LENGTH = 35;
    private const MAX_LOOKUPS = 32;
    // the second character of emoji sequences: ZWJ, keycap, VS16, skin tone, regional indicator or tag
    private const SEQUENCE_PATTERN = '/\G(?:\xE2\x80\x8D|\xE2\x83\xA3|\xEF\xB8\x8F|\xF0\x9F(?:\x8F[\xBB-\xBF]|\x87[\xA6-\xBF])|\xF3\xA0[\x80\x81])/';
    private const KEY_TAIL_BYTES = "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F\xA0\xA1\xA2\xA3\xA4\xA5\xA6\xA7\xA8\xA9\xAA\xAB\xAC\xAD\xAE\xAF\xB0\xB1\xB2\xB3\xB4\xB5\xB6\xB7\xB8\xB9\xBA\xBB\xBC\xBD\xBE\xBF\xE2\xEF\xF0\xF3";
    private const REVERSEABLE_IDS = [
        'emoji-github' => 'github-emoji',
        'emoji-gitlab' => 'gitlab-emoji',
        'emoji-slack' => 'slack-emoji',
        'emoji-text' => 'text-emoji',
        'github-emoji' => 'emoji-github',
        'gitlab-emoji' => 'emoji-gitlab',
        'slack-emoji' => 'emoji-slack',
        'text-emoji' => 'emoji-text',
    ];

    public readonly string $id;

    private array $map;

    private string $quickCheck;

    private \Transliterator $transliterator;

    public static function create(string $id, int $direction = self::FORWARD): self
    {
        $id = strtolower($id);

        if (!isset(self::REVERSEABLE_IDS[$id]) && !str_starts_with($id, 'emoji-')) {
            $id = 'emoji-'.$id;
        }

        if (self::REVERSE === $direction) {
            if (!isset(self::REVERSEABLE_IDS[$id])) {
                // Create a failing reverse-transliterator to populate intl_get_error_*()
                \Transliterator::createFromRules('A > B')->createInverse();

                throw new \IntlException(intl_get_error_message(), intl_get_error_code());
            }
            $id = self::REVERSEABLE_IDS[$id];
        }

        $file = __DIR__."/Resources/data/{$id}.php";
        if (!preg_match('/^[a-z0-9@_\\.\\-]*$/', $id) || !is_file($file) && !is_file($file .= '.gz')) {
            \Transliterator::create($id); // Populate intl_get_error_*()

            throw new \IntlException(intl_get_error_message(), intl_get_error_code());
        }

        /**
         * @var array<string, array> $maps
         */
        static $maps;

        // Create an instance of \Transliterator with a custom id; that's the only way
        static $newInstance;
        $instance = ($newInstance ??= (new \ReflectionClass(self::class))->newInstanceWithoutConstructor(...))();
        $instance->id = $id;
        $instance->map = $maps[$id] ??= str_ends_with($file, '.gz') ? GzipStreamWrapper::require($file) : require $file;

        return $instance;
    }

    public function createInverse(): self
    {
        return self::create($this->id, \Transliterator::REVERSE);
    }

    public function getErrorCode(): int
    {
        return isset($this->transliterator) ? $this->transliterator->getErrorCode() : 0;
    }

    public function getErrorMessage(): string
    {
        return isset($this->transliterator) ? $this->transliterator->getErrorMessage() : '';
    }

    public static function listIDs(): array
    {
        static $ids = [];

        if ($ids) {
            return $ids;
        }

        foreach (scandir(__DIR__.'/Resources/data/') as $file) {
            if (str_ends_with($file, '.php.gz')) {
                $ids[] = substr($file, 0, -7);
            } elseif (str_ends_with($file, '.php')) {
                $ids[] = substr($file, 0, -4);
            }
        }

        return $ids;
    }

    public function transliterate(string $string, int $start = 0, int $end = -1): string|false
    {
        $this->quickCheck ??= str_starts_with(array_key_first($this->map), ':') ? ':' : self::QUICK_CHECK;

        if (0 === $start && -1 === $end && preg_match('//u', $string)) {
            return \strlen($string) === strcspn($string, $this->quickCheck) ? $string : $this->replace($string);
        }

        // Here we rely on intl to validate the $string, $start and $end arguments
        // and to slice the string. Slicing is done by replacing the part of $string
        // between $start and $end by a unique cookie that can be reliably used to
        // identify which part of $string should be transliterated.

        static $cookie;
        static $transliterator;

        $cookie ??= hash('xxh128', random_bytes(8));
        $this->transliterator ??= clone $transliterator ??= \Transliterator::createFromRules('[:any:]* > '.$cookie);

        if (false === $result = $this->transliterator->transliterate($string, $start, $end)) {
            return false;
        }

        $parts = explode($cookie, $result);
        $start = \strlen($parts[0]);
        $length = -\strlen($parts[1]) ?: null;
        $string = substr($string, $start, $length);

        return $parts[0].(\strlen($string) === strcspn($string, $this->quickCheck) ? $string : $this->replace($string)).$parts[1];
    }

    /**
     * Avoids processing every map key when replacing sparse emoji.
     */
    private function replace(string $string): string
    {
        $result = '';
        $copied = 0;

        // shortcodes start and end with ":" and contain no other ":", so only one key can start at each ":"
        if (':' === $this->quickCheck) {
            for ($i = 0; false !== ($i = strpos($string, ':', $i)) && false !== $j = strpos($string, ':', 1 + $i); $i = $j) {
                if (isset($this->map[$key = substr($string, $i, 1 + $j - $i)])) {
                    $result .= substr($string, $copied, $i - $copied).$this->map[$key];
                    $copied = ++$j;
                }
            }

            return $result.substr($string, $copied);
        }

        // looking up emoji one by one is faster than strtr() only when there are not many of them
        if (self::MAX_LOOKUPS < substr_count($string, "\xF0") + substr_count($string, "\xE2") + substr_count($string, "\xE3") + substr_count($string, "\xC2")) {
            return strtr($string, $this->map);
        }

        // emoji start with one of these bytes, except keycaps, which start with "#", "*" or a digit followed by U+FE0F or U+20E3
        for ($i = 0, $length = \strlen($string); $length > $i += strcspn($string, "\xC2\xE2\xE3\xEF\xF0", $i);) {
            if ($i > $copied && str_contains('#*0123456789', $string[$i - 1]) && $keyLength = $this->matchAt($string, $i - 1)) {
                --$i;
            } elseif ("\xEF" === $string[$i] || !$keyLength = $this->matchAt($string, $i)) {
                ++$i;
                continue;
            }

            $result .= substr($string, $copied, $i - $copied).$this->map[substr($string, $i, $keyLength)];
            $i = $copied = $i + $keyLength;
        }

        return $result.substr($string, $copied);
    }

    /**
     * Returns the length of the longest key found at the given offset, or 0.
     */
    private function matchAt(string $string, int $offset): int
    {
        $keyLength = match (true) {
            "\x80" > $string[$offset] => 1,
            "\xE0" > $string[$offset] => 2,
            "\xF0" > $string[$offset] => 3,
            default => 4,
        };

        if (!preg_match(self::SEQUENCE_PATTERN, $string, offset: $offset + $keyLength)) {
            return isset($this->map[substr($string, $offset, $keyLength)]) ? $keyLength : 0;
        }

        // after their first character, emoji sequences only contain continuation bytes and the first bytes of U+20xx, U+FExx, U+1Fxxx and U+E00xx
        $keyLength = 1 + strspn($string, self::KEY_TAIL_BYTES, 1 + $offset, self::MAX_KEY_LENGTH - 1);

        for ($length = \strlen($string); $keyLength > 1; --$keyLength) {
            // keys end at a character boundary
            if (($offset + $keyLength === $length || "\x80" > $string[$offset + $keyLength] || "\xBF" < $string[$offset + $keyLength]) && isset($this->map[substr($string, $offset, $keyLength)])) {
                return $keyLength;
            }
        }

        return 0;
    }
}
