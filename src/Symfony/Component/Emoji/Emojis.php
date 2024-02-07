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

/**
 * @author Simon André <smn.andre@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
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
