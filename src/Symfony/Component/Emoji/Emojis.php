<?php

namespace Symfony\Component\Emoji;

final class Emojis
{
    /**
     * Checks if an emoji exists.
     */
    public static function exists(string $emoji): bool
    {
        foreach (self::getEmojis() as $value) {
            if ($emoji === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns all available emojis.
     *
    * @return list<string>
     */
    public static function getEmojis(): iterable
    {
        if (!is_file($dataFile = __DIR__ . '/Resources/data/emoji-en.php')) {
            throw new \RuntimeException(sprintf('The emoji data file "%s" does not exist.', $dataFile));
        }

        foreach (include $dataFile as $emoji => $name) {
            yield $emoji;
        }
    }
}
