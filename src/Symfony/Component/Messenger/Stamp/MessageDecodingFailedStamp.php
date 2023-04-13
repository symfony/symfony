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
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class MessageDecodingFailedStamp implements StampInterface
{
    public function __construct(private readonly string $message = '')
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
