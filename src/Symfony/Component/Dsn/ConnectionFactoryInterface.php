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

namespace Symfony\Component\Dsn;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
interface ConnectionFactoryInterface
{
    public static function create(string $dsn): object;

    public static function supports(string $dsn): bool;
}
