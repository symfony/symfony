<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Transliterator;

final class EmojiTransliterator
{
    private static array $transliteratorsByLocale = [];

    public static function getInstance(string $locale): \Transliterator
    {
        return self::$transliteratorsByLocale[$locale] ??= self::createTransliterator($locale);
    }

    private static function createTransliterator(string $locale): \Transliterator
    {
        if (!preg_match('/^[a-z0-9@_\\.\\-]*$/i', $locale)) {
            throw new \InvalidArgumentException(sprintf('Invalid "%s" locale.', $locale));
        }

        $rulesFilename = __DIR__."/../Resources/data/transliterator/emoji/$locale.txt";
        if (!is_file($rulesFilename)) {
            throw new \RuntimeException(sprintf('The transliterator rules source does not exist for locale "%s".', $locale));
        }

        if (!$transliterator = \Transliterator::createFromRules(file_get_contents($rulesFilename))) {
            throw new \RuntimeException(sprintf('Unable to create EmojiTransliterator instance: "%s".', intl_get_error_message()));
        }

        return $transliterator;
    }
}
