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
 * @author Teoh Han Hui <teohhanhui@gmail.com>
 */
class Php71Dummy
{
    public $string;

    public $stringOrNull;

    private $intPrivate;

    private $intWithAccessor;

    public function __construct(string $string, ?string $stringOrNull, int $intPrivate, int $intWithAccessor)
    {
        $this->string = $string;
        $this->stringOrNull = $stringOrNull;
        $this->intPrivate = $intPrivate;
        $this->intWithAccessor = $intWithAccessor;
    }

    public function getFoo(): ?array
    {
    }

    public function getBuz(): void
    {
    }

    public function setBar(?int $bar)
    {
    }

    public function addBaz(string $baz)
    {
    }

    public function getIntWithAccessor()
    {
        return $this->intWithAccessor;
    }
}
