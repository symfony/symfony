<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\EventListener\ClearSiteDataLogoutListener;

class ClearSiteDataLogoutListenerTest extends TestCase
{
    /**
     * @dataProvider provideClearSiteDataConfig
     */
    public function testLogout(array $clearSiteDataConfig, string $expectedHeader)
    {
        $response = new Response();
        $event = new LogoutEvent(new Request(), null);
        $event->setResponse($response);

        $listener = new ClearSiteDataLogoutListener($clearSiteDataConfig);

        $headerCountBefore = $response->headers->count();

        $listener->onLogout($event);

        $this->assertEquals(++$headerCountBefore, $response->headers->count());

        $this->assertNotNull($response->headers->get('Clear-Site-Data'));
        $this->assertEquals($expectedHeader, $response->headers->get('Clear-Site-Data'));
    }

    public static function provideClearSiteDataConfig(): iterable
    {
        yield [['*'], '"*"'];
        yield [['cache', 'cookies', 'storage', 'executionContexts'], '"cache", "cookies", "storage", "executionContexts"'];
    }
}
