<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\Consumer;

use Symfony\Component\Worker\MessageCollection;
use Symfony\Component\EventDispatcher\Event;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class MessageCollectionEvent extends Event
{
    private $messageCollection;

    public function __construct(MessageCollection $messageCollection)
    {
        $this->messageCollection = $messageCollection;
    }

    /**
     * @return MessageCollection
     */
    public function getMessageCollection()
    {
        return $this->messageCollection;
    }
}
