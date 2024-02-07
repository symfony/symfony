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
        return \in_array($emoji, self::getEmojis(), true);
    }

    /**
     * Returns all available emojis.
     *
     * @return array<string>
     */
    public static function getEmojis(): array
    {
        $dataFile = __DIR__.'/Resources/data/emojis.php';

        return is_file($dataFile) ? require $dataFile : GzipStreamWrapper::require($dataFile.'.gz');
    }
}
