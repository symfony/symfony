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
use Symfony\Component\Messenger\Stamp\NameStamp;

/**
 * Returns the message name as a topic.
 *
 * The message name is retrieved from the NameStamp attached to the message,
 * and will default to the message class if the stamp is not present.
 *
 * @author Pascal Luna <skalpa@zetareticuli.org>
 *
 * @experimental in 4.2
 */
class NameTopicsResolver implements TopicsResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function getTopics(Envelope $envelope): iterable
    {
        if (null !== $nameStamp = $envelope->get(NameStamp::class)) {
            yield $nameStamp->getName();
        } else {
            yield \get_class($envelope->getMessage());
        }
    }
}
