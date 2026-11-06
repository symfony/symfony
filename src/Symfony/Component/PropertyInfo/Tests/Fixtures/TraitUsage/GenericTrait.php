<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage;

/**
 * @template T
 */
trait GenericTrait
{
    /** @var T */
    public mixed $item;

    /** @var array{value: T, owner: self} */
    public array $details;

    /**
     * @param T $value
     */
    public function __construct(mixed $value = null)
    {
    }

    /**
     * @return T
     */
    public function getValue(): mixed
    {
        return null;
    }
}
