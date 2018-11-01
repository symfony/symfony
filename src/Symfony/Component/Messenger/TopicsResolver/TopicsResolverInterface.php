<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\TopicsResolver;

use Symfony\Component\Messenger\Envelope;

/**
 * Extracts topics from a message.
 *
 * The resolved topics will be used to locate handlers and senders for this message.
 *
 * @author Pascal Luna <skalpa@zetareticuli.org>
 *
 * @experimental in 4.2
 */
interface TopicsResolverInterface
{
    /**
     * Extracts topics from the given message.
     *
     * @return iterable|string[]
     */
    public function getTopics(Envelope $envelope): iterable;
}
