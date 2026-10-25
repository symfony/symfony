<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Clock\DatePoint;

class TypeInfoFormTypeGuesserCase
{
    public $untyped;
    public mixed $mixed;
    public string $string;
    public ?string $nullableString;
    public int $int;
    public ?int $nullableInt;
    public float $float;
    public bool $bool;
    public ?bool $nullableBool;
    public \DateTimeInterface $dateTimeInterface;
    public \DateTime $dateTime;
    public ?\DateTimeImmutable $dateTimeImmutable;
    public DatePoint $datePoint;
    public TypeInfoFormTypeGuesserCaseDateTime $dateTimeSubclass;
    public \DateInterval $dateInterval;
    public int|float $union;
    public array $array;
    public TypeInfoFormTypeGuesserCaseEnum $enum;
    private ?int $privateInt;

    /** @var positive-int */
    public int $positiveInt;
    /** @var int<0, 100>|null */
    public ?int $nullableIntRange;
    /** @var 1 */
    public int $intLiteral;
    /** @var 'draft' */
    public string $stringLiteral;
    /** @var non-zero-int */
    public int $nonZeroInt;
    /** @var 1|2|3 */
    public int $intLiteralUnion;
    /** @var 1|'one' */
    public int|string $mixedLiteralUnion;
}

class TypeInfoFormTypeGuesserCaseDateTime extends \DateTimeImmutable
{
}

enum TypeInfoFormTypeGuesserCaseEnum: string
{
    case Foo = 'foo';
}
