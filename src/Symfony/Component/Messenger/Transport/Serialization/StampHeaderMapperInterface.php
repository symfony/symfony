<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Serialization;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Maps stamps to plain headers and back, so that InteropSerializer can send stamps to another application.
 *
 * A mapper must not throw when it cannot read a header value. It must return no stamp for that value instead.
 * Stamps that implement NonSendableStampInterface are neither given to toHeaders() nor accepted from toStamps().
 */
interface StampHeaderMapperInterface
{
    /**
     * Returns the headers to send for the stamps of the envelope.
     *
     * @return array<string, string>
     */
    public function toHeaders(Envelope $envelope): array;

    /**
     * Returns the stamps to add to a received envelope.
     *
     * @param array<string, mixed> $headers The headers of the envelope as received, whose values can be of any type
     *
     * @return list<StampInterface>
     */
    public function toStamps(array $headers): array;
}
