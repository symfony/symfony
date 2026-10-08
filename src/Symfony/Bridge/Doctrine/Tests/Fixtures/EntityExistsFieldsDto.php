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

use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumInt;
use Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\EnumString;
use Symfony\Bridge\Doctrine\Validator\Constraints\EntityExists;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

class EntityExistsFieldsDto
{
    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'boolean')]
    public ?bool $boolean = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'integer')]
    #[Assert\Type('integer')]
    public ?int $integer = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'smallint')]
    public ?int $smallint = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'bigint')]
    public ?int $bigint = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'float')]
    public ?float $float = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'decimal')]
    public ?string $decimal = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'string')]
    public ?string $string = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'string')]
    #[Assert\Email]
    public ?string $email = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'asciiString')]
    public ?string $asciiString = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'guid')]
    public ?string $guid = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'date')]
    public ?\DateTime $date = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'datetime')]
    public ?\DateTimeInterface $datetime = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'datetimetz')]
    public ?\DateTime $datetimetz = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'time')]
    public ?\DateTime $time = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'dateImmutable')]
    public ?\DateTimeImmutable $dateImmutable = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'datetimeImmutable')]
    public ?\DateTimeImmutable $datetimeImmutable = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'datetimetzImmutable')]
    public ?\DateTimeImmutable $datetimetzImmutable = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'timeImmutable')]
    public ?\DateTimeImmutable $timeImmutable = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'dateinterval')]
    public ?\DateInterval $dateinterval = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'uuid')]
    public ?Uuid $uuid = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'ulid')]
    public ?Ulid $ulid = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'enumString')]
    public ?EnumString $enumString = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'enumInt')]
    public ?EnumInt $enumInt = null;

    // properties accepting the values of the field without declaring their exact type

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'integer')]
    public $untyped;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'date')]
    public mixed $mixed = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'uuid')]
    public mixed $uuidMixed = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'integer')]
    public string|int|null $integerAsUnion = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'dateImmutable')]
    public mixed $mixedImmutable = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'enumString')]
    public ?object $enumAsObject = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'string', groups: ['publish'])]
    public ?string $inAnotherGroup = null;

    // not guessed

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'integer')]
    public int $requiredInteger = 0;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'integer')]
    public ?float $integerAsFloat = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'integer')]
    public ?string $integerAsString = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'uuid')]
    public ?string $uuidAsString = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'text')]
    public ?string $text = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'enumArray')]
    public mixed $enumArray = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class)]
    public ?int $identifier = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'id')]
    public ?int $explicitIdentifier = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, repositoryMethod: 'findOneByString')]
    public ?string $repositoryMethod = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'simpleArray')]
    public ?array $simpleArray = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'json')]
    public ?array $json = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'association')]
    public ?int $association = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'unknown')]
    public ?string $unknownField = null;

    #[EntityExists(entityClass: 'Symfony\Bridge\Doctrine\Tests\Fixtures\DoesNotExist', identifierField: 'string')]
    public ?string $unknownEntity = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'string')]
    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'asciiString')]
    public ?string $repeated = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'date')]
    public ?string $dateAsString = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'string')]
    public ?int $stringAsInt = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'enumString')]
    public ?string $enumAsString = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'datetime')]
    public ?\DateTimeImmutable $mutableDateAsImmutable = null;

    public ?string $unconstrained = null;

    // a set hook accepting dates writes them to a string property
    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'date')]
    public ?string $dateThroughHook = null {
        set(\DateTimeInterface|string|null $value) {
            $this->dateThroughHook = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }
    }
}
