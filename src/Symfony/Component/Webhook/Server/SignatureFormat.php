<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Server;

/**
 * The signature scheme a webhook sender emits and a receiver requires.
 */
enum SignatureFormat: string
{
    /**
     * Symfony's historical "<algo>=<hex>" signature, over the event name, the id and the body.
     *
     * The event name travels in the "Webhook-Event" header, which the signature covers.
     */
    case Legacy = 'legacy';

    /**
     * The Standard Webhooks "v1,<base64>" signature, over the id, the timestamp and the body.
     *
     * The signature covers no header, so the event name travels in the payload's "type" key and
     * the "Webhook-Event" header is not sent at all.
     *
     * @see https://www.standardwebhooks.com/
     */
    case Standard = 'standard';

    /**
     * Both signatures, space separated, with the event name in both the header and the payload.
     *
     * Meant for a migration window: a receiver accepts either, and only the Standard Webhooks
     * entry bounds replays.
     */
    case Transitional = 'transitional';
}
