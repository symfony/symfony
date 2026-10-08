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

use Symfony\Bridge\Doctrine\Validator\Constraints\EntityExists;

/**
 * Properties written through setters, to be extended.
 */
abstract class EntityExistsFieldsParentDto
{
    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'enumString')]
    private ?string $enumThroughStringSetter = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'string')]
    private ?string $stringThroughNonNullableSetter = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'string')]
    private ?string $stringThroughSetter = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'date')]
    private $untypedDateThroughStringSetter;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'string')]
    private ?string $notWritable = null;

    #[EntityExists(entityClass: EntityExistsFieldsEntity::class, identifierField: 'string')]
    private ?string $protectedSetter = null;

    public function getEnumThroughStringSetter(): ?string
    {
        return $this->enumThroughStringSetter;
    }

    public function setEnumThroughStringSetter(?string $value): void
    {
        $this->enumThroughStringSetter = $value;
    }

    public function getStringThroughNonNullableSetter(): ?string
    {
        return $this->stringThroughNonNullableSetter;
    }

    public function setStringThroughNonNullableSetter(string $value): void
    {
        $this->stringThroughNonNullableSetter = $value;
    }

    public function getStringThroughSetter(): ?string
    {
        return $this->stringThroughSetter;
    }

    public function setStringThroughSetter(?string $value): void
    {
        $this->stringThroughSetter = $value;
    }

    public function getUntypedDateThroughStringSetter()
    {
        return $this->untypedDateThroughStringSetter;
    }

    public function setUntypedDateThroughStringSetter(?string $value): void
    {
        $this->untypedDateThroughStringSetter = $value;
    }

    public function getNotWritable(): ?string
    {
        return $this->notWritable;
    }

    public function getProtectedSetter(): ?string
    {
        return $this->protectedSetter;
    }

    protected function setProtectedSetter(?string $value): void
    {
        $this->protectedSetter = $value;
    }
}
