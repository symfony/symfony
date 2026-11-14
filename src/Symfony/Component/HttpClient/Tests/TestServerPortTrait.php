<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

trait TestServerPortTrait
{
    /**
     * Moves the given port by the same offset as the test server when the SYMFONY_HTTP_CLIENT_TEST_PORT env var is set.
     */
    protected static function port(int $port = 8057): int
    {
        return $port - 8057 + (int) (getenv('SYMFONY_HTTP_CLIENT_TEST_PORT') ?: 8057);
    }
}
