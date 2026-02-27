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
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 *
 * @see https://doc.lox24.eu/#section/Introduction/Notifications
 */
final class Lox24RequestParser extends AbstractRequestParser
{
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new MethodRequestMatcher('POST');
    }

    /**
     * @throws RejectWebhookException
     */
    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): SmsEvent|RemoteEvent|null
    {
        $payload = $request->request->all() ?? [];
        $name = $payload['name'] ?? null;
        $data = $payload['data'] ?? null;
        $id = $payload['id'] ?? null;

        $missingFields = [];
        if (!$id) {
            $missingFields[] = 'id';
        }
        if (!$name) {
            $missingFields[] = 'name';
        }
        if (!$data) {
            $missingFields[] = 'data';
        }

        if ($missingFields) {
            throw new RejectWebhookException(406, \sprintf('The required fields "%s" are missing from the payload.', implode('", "', $missingFields)));
        }

        if (!\is_array($data)) {
            throw new RejectWebhookException(406, 'The "data" field must be an array.');
        }

        return $this->createEventFromPayload($name, $data, $payload);
    }

    private function createEventFromPayload(string $eventName, array $data, array $payload): SmsEvent|RemoteEvent|null
    {
        return match ($eventName) {
            'sms.delivery', 'sms.delivery.dryrun' => $this->createDeliveryEvent($data, $payload),
            default => new RemoteEvent($eventName, $payload['id'], $payload),
        };
    }

    private function createDeliveryEvent(array $data, array $payload): ?SmsEvent
    {
        if (!isset($data['id'])) {
            throw new RejectWebhookException(406, 'The required field "id" is missing from the delivery event payload.');
        }

        $statusCode = $data['status_code'] ?? null;
        $dlrCode = $data['dlr_code'] ?? null;

        if (null !== $dlrCode) {
            return $this->createEventFromDlrCode($dlrCode, $data, $payload);
        }
        if (null !== $statusCode) {
            return $this->createEventFromStatusCode($statusCode, $data, $payload);
        }

        throw new RejectWebhookException(406, 'The required field "status_code" or "dlr_code" is missing from the delivery event payload.');
    }

    private function createEventFromDlrCode(int $dlrCode, array $data, array $payload): ?SmsEvent
    {
        $eventType = match ($dlrCode) {
            0 => null,
            1 => SmsEvent::DELIVERED,
            2 => null,
            4 => null,
            default => SmsEvent::FAILED,
        };

        if (null === $eventType) {
            return null;
        }

        return new SmsEvent($eventType, $data['id'], $payload);
    }

    private function createEventFromStatusCode(int $statusCode, array $data, array $payload): ?SmsEvent
    {
        if (0 === $statusCode) {
            return null;
        }

        $eventType = match ($statusCode) {
            100 => SmsEvent::DELIVERED,
            default => SmsEvent::FAILED,
        };

        return new SmsEvent($eventType, $data['id'], $payload);
    }
}
