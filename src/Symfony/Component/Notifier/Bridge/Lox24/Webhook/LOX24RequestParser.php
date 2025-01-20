<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Lox24\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 *
 * @see https://doc.lox24.eu/#section/Introduction/Notifications
 */
final class LOX24RequestParser extends AbstractRequestParser
{
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new MethodRequestMatcher('POST');
    }

    /**
     * @throws RejectWebhookException
     */
    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?SmsEvent
    {
        $payload = $request->request->all() ?? [];
        $name = $payload['name'] ?? null;
        $data = $payload['data'] ?? [];

        if ('sms.delivery' !== $name) {
            throw new RejectWebhookException(400, 'Notification name is not \'sms.delivery\'');
        }

        if (!isset($data['id'], $data['status_code'])) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        $code = $data['status_code'];

        if (0 === $code) {
            return null;
        }

        $name = 100 === $code ? SmsEvent::DELIVERED : SmsEvent::FAILED;

        return new SmsEvent($name, $data['id'], $payload);
    }
}
