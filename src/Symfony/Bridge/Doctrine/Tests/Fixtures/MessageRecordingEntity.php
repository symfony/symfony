<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Symfony\Bridge\Doctrine\Messenger\MessageRecordingEntityInterface;
use Symfony\Bridge\Doctrine\Messenger\MessageRecordingEntityTrait;

final class MessageRecordingEntity implements MessageRecordingEntityInterface
{
    use MessageRecordingEntityTrait;

    public function doRecordMessage(object $message): void
    {
        $this->recordMessage($message);
    }
}
