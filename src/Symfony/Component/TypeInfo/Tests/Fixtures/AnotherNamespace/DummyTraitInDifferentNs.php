<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures\AnotherNamespace;

/**
 * @template T
 *
 * @phpstan-type DummyList = list<DummyInDifferentNs>
 */
trait DummyTraitInDifferentNs
{
    /**
     * @var list<T>
     */
    public array $items = [];

    /**
     * @var DummyList
     */
    public array $dummies = [];

    /**
     * @var self
     */
    public mixed $self;

    /**
     * @var parent
     */
    public mixed $parent;

    /**
     * @param DummyInDifferentNs $dummy
     *
     * @return DummyInDifferentNs
     */
    public function getNextDummy(mixed $dummy): mixed
    {
        throw new \BadMethodCallException(\sprintf('"%s" is not implemented.', __METHOD__));
    }
}
