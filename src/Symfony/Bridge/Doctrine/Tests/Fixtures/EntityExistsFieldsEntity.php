<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumInt;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumString;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EntityExistsFieldsRepository::class)]
class EntityExistsFieldsEntity
{
    #[ORM\Id, ORM\Column]
    public int $id;

    #[ORM\Column(type: 'boolean')]
    public bool $boolean;

    #[ORM\Column(type: 'integer')]
    public int $integer;

    #[ORM\Column(type: 'smallint')]
    public int $smallint;

    #[ORM\Column(type: 'bigint')]
    public int $bigint;

    #[ORM\Column(type: 'float')]
    public float $float;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    public string $decimal;

    #[ORM\Column(type: 'string', nullable: true)]
    public ?string $string;

    #[ORM\Column(type: 'ascii_string')]
    public string $asciiString;

    #[ORM\Column(type: 'text')]
    public string $text;

    #[ORM\Column(type: 'guid')]
    public string $guid;

    #[ORM\Column(type: 'date')]
    public \DateTime $date;

    #[ORM\Column(type: 'datetime')]
    public \DateTime $datetime;

    #[ORM\Column(type: 'datetimetz')]
    public \DateTime $datetimetz;

    #[ORM\Column(type: 'time')]
    public \DateTime $time;

    #[ORM\Column(type: 'date_immutable')]
    public \DateTimeImmutable $dateImmutable;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $datetimeImmutable;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public \DateTimeImmutable $datetimetzImmutable;

    #[ORM\Column(type: 'time_immutable')]
    public \DateTimeImmutable $timeImmutable;

    #[ORM\Column(type: 'dateinterval')]
    public \DateInterval $dateinterval;

    #[ORM\Column(type: 'uuid')]
    public Uuid $uuid;

    #[ORM\Column(type: 'ulid')]
    public Ulid $ulid;

    #[ORM\Column(enumType: EnumString::class)]
    public EnumString $enumString;

    #[ORM\Column(enumType: EnumInt::class)]
    public EnumInt $enumInt;

    #[ORM\Column(type: 'simple_array')]
    public array $simpleArray;

    #[ORM\Column(type: 'simple_array', enumType: EnumString::class)]
    public array $enumArray;

    #[ORM\Column(type: 'json')]
    public array $json;

    #[ORM\ManyToOne]
    public ?SingleIntIdEntity $association = null;
}
