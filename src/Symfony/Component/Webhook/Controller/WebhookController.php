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
 * @experimental in 6.3
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
            return new Response(sprintf('No parser found for webhook of type "%s".', $type), 404);
        }
        /** @var RequestParserInterface $parser */
        $parser = $this->parsers[$type]['parser'];

        if (!$event = $parser->parse($request, $this->parsers[$type]['secret'])) {
            return $parser->createRejectedResponse('Unable to parse the webhook payload.');
        }

        $this->bus->dispatch(new ConsumeRemoteEventMessage($type, $event));

        return $parser->createSuccessfulResponse();
    }
}
