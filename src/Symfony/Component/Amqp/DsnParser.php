<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Amqp;

/**
 * @internal
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class DsnParser
{
    public static function parseDsn(string $dns): array
    {
        $parts = parse_url($dns);

        return array(
            'host' => $parts['host'] ?? 'localhost',
            'login' => $parts['user'] ?? 'guest',
            'password' => $parts['pass'] ?? 'guest',
            'port' => $parts['port'] ?? 5672,
            'vhost' => isset($parts['path'][1]) ? substr($parts['path'], 1) : '/',
        );
    }
}
