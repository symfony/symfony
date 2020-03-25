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
 * Added by the user to signify this message is meant to be only one of this unique message inside
 * of the message queue. This can be used to prevent enqueueing the same message awaiting to be processed.
 * If no id is provided, the transport should generate a unique identifier based off of the content of the message.
 *
 * @author RJ Garcia <ragboyjr@icloud.com>
 */
final class UniqueStamp implements StampInterface
{
    private $id;

    public function __construct(?string $id = null)
    {
        $this->id = $id;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
