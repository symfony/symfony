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
class Php7Dummy extends Php7ParentDummy
{
    public function getFoo(): array
    {
    }

    public function setBar(int $bar)
    {
    }

    public function addBaz(string $baz)
    {
    }

    public function getBuz(): self
    {
    }

    public function getBiz(): parent
    {
    }
}
