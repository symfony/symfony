<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Dsn\Exception;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class DsnTypeNotSupported extends InvalidDsnException
{
    public static function onlyUrl($dsn): self
    {
        return new self($dsn, 'Only DSNs of type "URL" is supported.');
    }

    public static function onlyPath($dsn): self
    {
        return new self($dsn, 'Only DSNs of type "path" is supported.');
    }
}
