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
     * @return iterable<string>
     */
    public static function getEmojis(): iterable
    {
        $dataFile = __DIR__.'/Resources/data/emoji-en.php';
        if (!is_file($dataFile) && !is_file($dataFile.'.gz')) {
            throw new \RuntimeException(sprintf('The emoji data file "%s" does not exist.', $dataFile));
        }

        $emojis = is_file($dataFile) ? require $dataFile : GzipStreamWrapper::require($dataFile.'.gz');
        foreach ($emojis as $emoji => $name) {
            yield $emoji;
        }
    }
}
