<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox;

use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Mode;
use Symfony\Component\Notifier\Bridge\Smsbox\Enum\Strategy;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Alan Zarli <azarli@smsbox.fr>
 * @author Farid Touil <ftouil@smsbox.fr>
 */
final class SmsboxTransport extends AbstractTransport
{
    protected const HOST = 'api.smsbox.pro';

    public function __construct(
        #[\SensitiveParameter] private string $apiKey,
        private Mode $mode,
        private Strategy $strategy,
        private ?string $sender,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $dsn = \sprintf('smsbox://%s?mode=%s&strategy=%s', $this->getEndpoint(), $this->mode->value, $this->strategy->value);

        if (Mode::Expert === $this->mode) {
            $dsn .= '&sender='.$this->sender;
        }

        return $dsn;
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof SmsboxOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $phoneCleaned = preg_replace('/[^0-9+]+/', '', $message->getPhone());
        if (!preg_match("/^(\+|)[0-9]{7,14}$/", $phoneCleaned)) {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['dest'] = $phoneCleaned;
        $options['msg'] = $message->getSubject();
        $options['id'] = 1;
        $options['usage'] = 'symfony';
        $options['mode'] ??= $this->mode->value;
        $options['strategy'] ??= $this->strategy->value;

        if (Mode::Expert === $options['mode']) {
            $options['origine'] = $options['sender'] ?? $this->sender;
        }
        unset($options['sender']);

        if (isset($options['daysMinMax'])) {
            [$options['day_min'], $options['day_max']] = $options['daysMinMax'];
            unset($options['daysMinMax']);
        }

        if (isset($options['hoursMinMax'])) {
            [$options['hour_min'], $options['hour_max']] = $options['hoursMinMax'];
            unset($options['hoursMinMax']);
        }

        if (isset($options['dateTime'])) {
            $options['date'] = $options['dateTime']->format('d/m/Y');
            $options['heure'] = $options['dateTime']->format('H:i');

            unset($options['dateTime']);
        }

        if (isset($options['variable'])) {
            preg_match_all('%([0-9]+)%', $options['msg'], $matches);
            $occurrenceValMsg = $matches[0];
            $occurrenceValMsgMax = (int) max($occurrenceValMsg);

            if ($occurrenceValMsgMax !== \count($options['variable'])) {
                throw new InvalidArgumentException(\sprintf('You must have the same amount of index in your array as you have variable. Expected %d variable, got %d.', $occurrenceValMsgMax, \count($options['variable'])));
            }

            $t = str_replace([',', ';'], ['%d44%', '%d59%'], $options['variable']);
            $variableStr = implode(';', $t);
            $options['dest'] .= ';'.$variableStr;
            $options['personnalise'] = 1;

            unset($options['variable']);
        }

        $response = $this->client->request('POST', \sprintf('https://%s/1.1/api.php', $this->getEndpoint()), [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'App '.$this->apiKey,
            ],
            'body' => $options,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Smsbox server.', $response, previous: $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException(\sprintf('Unable to send the SMS: "%s" (%s).', $error['description'], $error['code']), $response);
        }

        $body = $response->getContent(false);
        if (!preg_match('/^OK .*/', $body)) {
            throw new TransportException(\sprintf('Unable to send the SMS: "%s" (%s).', $body, 400), $response);
        }

        if (!preg_match('/^OK (\d+)/', $body, $reference)) {
            throw new TransportException(\sprintf('Unable to send the SMS: "%s" (%s).', $body, 400), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($reference[1]);

        return $sentMessage;
    }
}
