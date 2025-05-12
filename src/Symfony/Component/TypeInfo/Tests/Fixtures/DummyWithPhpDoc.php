<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

/**
 * @phpstan-type CustomInt = int
 * @psalm-type PsalmCustomInt = int
 */
final class DummyWithPhpDoc
{
    /**
     * @var array<Dummy>
     */
    public mixed $arrayOfDummies = [];

    /**
     * @var CustomInt
     */
    public mixed $aliasedInt;

    /**
     * @param bool $promoted
     * @param bool $promotedVarAndParam
     */
    public function __construct(
        public mixed $promoted,
        /**
         * @var string
         */
        public mixed $promotedVar,
        /**
         * @var string
         */
        public mixed $promotedVarAndParam,
    ) {
    }

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
