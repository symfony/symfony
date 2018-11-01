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
 * Returns the list of classes and interfaces implemented by the message as topics.
 *
 * @author Pascal Luna <skalpa@zetareticuli.org>
 *
 * @experimental in 4.2
 */
class ClassTopicsResolver implements TopicsResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function getTopics(Envelope $envelope): iterable
    {
        $messageClass = \get_class($envelope->getMessage());

        yield $messageClass;

        foreach (class_parents($messageClass) as $parentClass) {
            yield $parentClass;
        }

        foreach (class_implements($messageClass) as $interfaceName) {
            yield $interfaceName;
        }
    }
}
