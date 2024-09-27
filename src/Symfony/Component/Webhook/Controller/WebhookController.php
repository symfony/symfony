<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RemoteEvent\Messenger\ConsumeRemoteEventMessage;
use Symfony\Component\Webhook\Client\RequestParserInterface;

/**
 * Receives webhooks from a variety of third-party providers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class WebhookController
{
    public function __construct(
        /** @var array<string, array{parser: RequestParserInterface, secret: string}> $parsers */
        private readonly array $parsers,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function handle(string $type, Request $request): Response
    {
        if (!isset($this->parsers[$type])) {
            return new Response('No webhook parser found for the type given in the URL.', 404, ['Content-Type' => 'text/plain']);
        }
        /** @var RequestParserInterface $parser */
        $parser = $this->parsers[$type]['parser'];
        $events = $parser->parse($request, $this->parsers[$type]['secret']);

        if (!$events) {
            return $parser->createRejectedResponse('Unable to parse the webhook payload.', $request);
        }

        $events = \is_array($events) ? $events : [$events];

        foreach ($events as $event) {
            $this->bus->dispatch(new ConsumeRemoteEventMessage($type, $event));
        }

        return $parser->createSuccessfulResponse($request);
    }
}
