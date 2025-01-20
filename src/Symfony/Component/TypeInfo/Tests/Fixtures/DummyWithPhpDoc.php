<?php

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

final class DummyWithPhpDoc
{
    /**
     * @var array<Dummy>
     */
    public mixed $arrayOfDummies = [];

    /**
     * @param Dummy $dummy
     *
     * @return Dummy
     */
    public function getNextDummy(mixed $dummy): mixed
    {
        throw new \BadMethodCallException(sprintf('"%s" is not implemented.', __METHOD__));
    }
}
