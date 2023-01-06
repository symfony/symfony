<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Php74Dummy
{
    public Dummy $dummy;
    private ?bool $nullableBoolProp;
    /** @var string[] */
    private array $stringCollection;
    private ?int $nullableWithDefault = 1;
    public array $collection = [];

    public function addStringCollection(string $string): void
    {
    }

    public function removeStringCollection(string $string): void
    {
    }
}
