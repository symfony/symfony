<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MarshallingSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MigratingSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;

class SessionIdInterfaceTest extends TestCase
{
    #[DataProvider('provideHandlers')]
    public function testHandlersCreateSessionIds(\SessionHandlerInterface $handler)
    {
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9,-]{22,}$/', $handler->create_sid());
    }

    public static function provideHandlers(): iterable
    {
        yield 'null' => [new NullSessionHandler()];
        yield 'strict' => [new StrictSessionHandler(new \SessionHandler())];
        yield 'migrating' => [new MigratingSessionHandler(new NullSessionHandler(), new NullSessionHandler())];
        yield 'proxy' => [new SessionHandlerProxy(new NullSessionHandler())];

        if (class_exists(DefaultMarshaller::class)) {
            yield 'marshalling' => [new MarshallingSessionHandler(new NullSessionHandler(), new DefaultMarshaller())];
        }
    }
}
