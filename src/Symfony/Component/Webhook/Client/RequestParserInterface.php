<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Client;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
interface RequestParserInterface
{
    /**
     * Parses an HTTP Request and converts it into a RemoteEvent.
     *
     * @return ?RemoteEvent Returns null if the webhook must be ignored
     *
     * @throws RejectWebhookException When the payload is rejected (signature issue, parse issue, ...)
     */
    public function parse(Request $request, string $secret): ?RemoteEvent;

    public function createSuccessfulResponse(): Response;

    public function createRejectedResponse(string $reason): Response;
}
