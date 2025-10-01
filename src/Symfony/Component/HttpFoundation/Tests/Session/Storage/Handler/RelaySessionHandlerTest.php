<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Session\Storage\Handler;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Relay\Relay;
use Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler\AbstractRedisSessionHandlerTestCase;

#[RequiresPhpExtension('relay')]
#[Group('integration')]
class RelaySessionHandlerTest extends AbstractRedisSessionHandlerTestCase
{
    protected function createRedisClient(string $host): Relay
    {
        return new Relay(...explode(':', $host));
    }
}
