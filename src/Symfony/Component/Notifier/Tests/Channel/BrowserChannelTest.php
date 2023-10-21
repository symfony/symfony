<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Channel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Notifier\Channel\BrowserChannel;
use Symfony\Component\Notifier\Exception\FlashMessageImportanceMapperException;
use Symfony\Component\Notifier\FlashMessage\BootstrapFlashMessageImportanceMapper;
use Symfony\Component\Notifier\FlashMessage\DefaultFlashMessageImportanceMapper;
use Symfony\Component\Notifier\FlashMessage\FlashMessageImportanceMapperInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * @author Ben Roberts <ben@headsnet.com>
 */
class BrowserChannelTest extends TestCase
{
    /**
     * @dataProvider defaultFlashMessageImportanceDataProvider
     */
    public function testImportanceLevelIsReflectedInFlashMessageType(
        FlashMessageImportanceMapperInterface $mapper,
        string $importance,
        string $expectedFlashMessageType
    ) {
        $session = $this->createMock(Session::class);
        $session->method('getFlashBag')->willReturn(new FlashBag());
        $browserChannel = $this->buildBrowserChannel($session, $mapper);
        $notification = new Notification();
        $notification->importance($importance);
        $recipient = new Recipient('hello@example.com');

        $browserChannel->notify($notification, $recipient);

        $this->assertEquals($expectedFlashMessageType, array_key_first($session->getFlashBag()->all()));
    }

    public function testUnknownImportanceMappingIsReported()
    {
        $session = $this->createMock(Session::class);
        $session->method('getFlashBag')->willReturn(new FlashBag());
        $browserChannel = $this->buildBrowserChannel($session, new DefaultFlashMessageImportanceMapper());
        $notification = new Notification();
        $notification->importance('unknown-importance-string');
        $recipient = new Recipient('hello@example.com');

        $this->expectException(FlashMessageImportanceMapperException::class);

        $browserChannel->notify($notification, $recipient);
    }

    public static function defaultFlashMessageImportanceDataProvider(): array
    {
        return [
            [new DefaultFlashMessageImportanceMapper(), Notification::IMPORTANCE_URGENT, 'notification'],
            [new DefaultFlashMessageImportanceMapper(), Notification::IMPORTANCE_HIGH, 'notification'],
            [new DefaultFlashMessageImportanceMapper(), Notification::IMPORTANCE_MEDIUM, 'notification'],
            [new DefaultFlashMessageImportanceMapper(), Notification::IMPORTANCE_LOW, 'notification'],
            [new BootstrapFlashMessageImportanceMapper(), Notification::IMPORTANCE_URGENT, 'danger'],
            [new BootstrapFlashMessageImportanceMapper(), Notification::IMPORTANCE_HIGH, 'warning'],
            [new BootstrapFlashMessageImportanceMapper(), Notification::IMPORTANCE_MEDIUM, 'info'],
            [new BootstrapFlashMessageImportanceMapper(), Notification::IMPORTANCE_LOW, 'success'],
        ];
    }

    private function buildBrowserChannel(Session $session, FlashMessageImportanceMapperInterface $mapper): BrowserChannel
    {
        $request = $this->createMock(Request::class);
        $request->method('getSession')->willReturn($session);
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        return new BrowserChannel($requestStack, $mapper);
    }
}
