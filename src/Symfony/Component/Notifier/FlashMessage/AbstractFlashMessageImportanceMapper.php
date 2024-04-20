<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\FlashMessage;

use Symfony\Component\Notifier\Exception\FlashMessageImportanceMapperException;

/**
 * @author Ben Roberts <ben@headsnet.com>
 */
abstract class AbstractFlashMessageImportanceMapper
{
    public function flashMessageTypeFromImportance(string $importance): string
    {
        if (!\array_key_exists($importance, static::IMPORTANCE_MAP)) {
            throw new FlashMessageImportanceMapperException($importance, static::class);
        }

        return static::IMPORTANCE_MAP[$importance];
    }
}
