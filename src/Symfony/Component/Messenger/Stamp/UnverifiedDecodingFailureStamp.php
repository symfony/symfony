<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * Marks a decoding failure whose signature could not be verified.
 *
 * The envelope it carries can still decode to a signed message, as a claim reference does: the stamps the failure was decoded with must not reach that message.
 *
 * @internal
 */
final class UnverifiedDecodingFailureStamp implements NonSendableStampInterface
{
    /**
     * @param list<StampInterface> $stamps             The stamps the failure was decoded with
     * @param list<class-string>   $signedMessageTypes The message types that require a signature
     */
    public function __construct(
        public readonly array $stamps,
        private array $signedMessageTypes,
    ) {
    }

    public function requiresSignature(object $message): bool
    {
        foreach ($this->signedMessageTypes as $type) {
            if ($message instanceof $type) {
                return true;
            }
        }

        return false;
    }
}
