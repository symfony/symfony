<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\TopicsResolver\TopicsResolverInterface;

/**
 * Chains multiple topics resolvers together.
 *
 * Results are de-duplicated to prevent the same topic from being
 * returned multiple times.
 *
 * @author Pascal Luna <skalpa@zetareticuli.org>
 *
 * @experimental in 4.2
 */
class ChainTopicsResolver implements TopicsResolverInterface
{
    private $resolvers;

    /**
     * @param iterable|TopicsResolverInterface[] $resolvers
     */
    public function __construct(iterable $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    /**
     * {@inheritdoc}
     */
    public function getTopics(Envelope $envelope): iterable
    {
        $seen = array();

        foreach ($this->resolvers as $resolver) {
            foreach ($resolver->getTopics($envelope) as $topic) {
                if (!isset($seen[$topic])) {
                    yield $seen[$topic] = $topic;
                }
            }
        }
    }
}
