<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IpsRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class SmsboxRequestParser extends AbstractRequestParser
{
    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([
            new MethodRequestMatcher(['GET']),
            new IpsRequestMatcher([
                '37.59.198.135',
                '178.33.185.51',
                '54.36.93.79',
                '54.36.93.80',
                '62.4.31.47',
                '62.4.31.48',
            ]),
        ]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?SmsEvent
    {
        $payload = $request->query->all();

        if (
            !isset($payload['numero'])
            || !isset($payload['reference'])
            || !isset($payload['accuse'])
            || !isset($payload['ts'])
        ) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        $name = match ($payload['accuse']) {
            // Documentation for SMSBOX dlr code https://www.smsbox.net/en/tools-development#doc-sms-accusees
            '-3' => SmsEvent::FAILED,
            '-1' => null,
            '0' => SmsEvent::DELIVERED,
            '1' => SmsEvent::FAILED,
            '2' => SmsEvent::FAILED,
            '3' => SmsEvent::FAILED,
            '4' => SmsEvent::FAILED,
            '5' => SmsEvent::FAILED,
            '6' => SmsEvent::FAILED,
            '7' => SmsEvent::FAILED,
            '8' => SmsEvent::FAILED,
            '9' => null,
            '10' => null,
            default => throw new RejectWebhookException(406, \sprintf('Unknown status: %s', $payload['accuse'])),
        };

        $event = new SmsEvent($name, $payload['reference'], $payload);
        $event->setRecipientPhone($payload['numero']);

        return $event;
    }
}
