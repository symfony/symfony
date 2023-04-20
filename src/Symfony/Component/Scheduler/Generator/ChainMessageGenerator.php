<?php

namespace Symfony\Component\Scheduler\Generator;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class ChainMessageGenerator implements MessageGeneratorInterface
{
    /**
     * @param MessageGeneratorInterface[] $generators
     */
    public function __construct(private iterable $generators)
    {
    }

    public function getMessages(): iterable
    {
        foreach ($this->generators as $generator) {
            yield from $generator->getMessages();
        }
    }
}
