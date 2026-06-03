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
 * Added by a sender or receiver to indicate the id of this message in that transport.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class TransportMessageIdStamp implements StampInterface
{
    /**
     * @param mixed $id some "identifier" of the message in a transport
     */
    public function __construct(
        private mixed $id,
    ) {
    }

    public function getId(): mixed
    {
        return $this->id;
    }
}
