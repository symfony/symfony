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

/**
 * Implemented by serializers that can tell the class of the carried message
 * from the encoded envelope, without instantiating the payload.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface MessageTypeAwareSerializerInterface
{
    /**
     * Returns the FQCN of the message carried by the encoded envelope.
     *
     * Implementations MUST determine the class from the encoded metadata only:
     * they MUST NOT unserialize or otherwise instantiate the payload, and MUST
     * be side-effect free.
     *
     * Returning null signals that the envelope is not recognizable under these
     * constraints; consumers may treat such an envelope as untrusted (e.g. refuse
     * to decode it unless it carries a valid signature). Implementations SHOULD
     * therefore return the actual class for every well-formed envelope they can
     * produce, reserving null for malformed or unrecognized input.
     *
     * @param array{body: string, headers?: array<string, string>} $encodedEnvelope
     *
     * @return class-string|null
     */
    public function getMessageType(array $encodedEnvelope): ?string;
}
