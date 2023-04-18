<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Uid\Uuid;

final class Php74Full
{
    public string $string;
    public int $int;
    public float $float;
    public bool $bool;
    public \DateTime $dateTime;
    public \DateTimeImmutable $dateTimeImmutable;
    public \DateTimeZone $dateTimeZone;
    public \SplFileInfo $splFileInfo;
    public Uuid $uuid;
    public array $array;
    /** @var Php74Full[] */
    public array $collection;
    public Php74FullWithConstructor $php74FullWithConstructor;
    public Php74FullWithTypedConstructor $php74FullWithTypedConstructor;
    public DummyMessageInterface $dummyMessage;
    /** @var TestFoo[] $nestedArray */
    public TestFoo $nestedObject;
    /** @var Php74Full[] */
    public $anotherCollection;
}

final class Php74FullWithConstructor
{
    public function __construct($constructorArgument)
    {
    }
}

final class Php74FullWithTypedConstructor
{
    public function __construct(float $something)
    {
    }
}

final class TestFoo
{
    public int $int;
}
