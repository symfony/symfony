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

    public function getErrorCode(): int|false
    {
        return isset($this->transliterator) ? $this->transliterator->getErrorCode() : 0;
    }

    public function getErrorMessage(): string|false
    {
        return isset($this->transliterator) ? $this->transliterator->getErrorMessage() : false;
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
            return \strlen($string) === strcspn($string, $this->quickCheck) ? $string : strtr($string, $this->map);
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

        return $parts[0].(\strlen($string) === strcspn($string, $this->quickCheck) ? $string : strtr($string, $this->map)).$parts[1];
    }
}
