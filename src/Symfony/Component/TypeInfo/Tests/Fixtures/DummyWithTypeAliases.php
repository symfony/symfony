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
 * @phpstan-type CustomArray = array{0: CustomInt, 1: CustomString, 2: bool}
 * @phpstan-type CustomString = string
 *
 * @phpstan-import-type CustomInt from DummyWithPhpDoc
 * @phpstan-import-type CustomInt from DummyWithPhpDoc as AliasedCustomInt
 *
 * @psalm-type PsalmCustomArray = array{0: PsalmCustomInt, 1: PsalmCustomString, 2: bool}
 * @psalm-type PsalmCustomString = string
 *
 * @psalm-import-type PsalmCustomInt from DummyWithPhpDoc
 * @psalm-import-type PsalmCustomInt from DummyWithPhpDoc as PsalmAliasedCustomInt
 */
final class DummyWithTypeAliases
{
    /**
     * @var CustomString
     */
    public mixed $localAlias;

    /**
     * @var CustomInt
     */
    public mixed $externalAlias;

    /**
     * @var AliasedCustomInt
     */
    public mixed $aliasedExternalAlias;

    /**
     * @var PsalmCustomString
     */
    public mixed $psalmLocalAlias;

    /**
     * @var PsalmCustomInt
     */
    public mixed $psalmExternalAlias;

    /**
     * @var PsalmAliasedCustomInt
     */
    public mixed $psalmOtherAliasedExternalAlias;
}

/**
 * @phpstan-import-type CustomInt from DummyWithPhpDoc
 */
final class DummyWithImportedOnlyTypeAliases
{
    /**
     * @var CustomInt
     */
    public mixed $externalAlias;
}

/**
 * @phpstan-type Foo = array{0: Bar}
 * @phpstan-type Bar = array{0: Foo}
 */
final class DummyWithRecursiveTypeAliases
{
}

/**
 * @phpstan-type Invalid = SomethingInvalid
 */
final class DummyWithInvalidTypeAlias
{
}

/**
 * @phpstan-import-type Invalid from DummyWithTypeAliases
 */
final class DummyWithInvalidTypeAliasImport
{
}

/**
 * @phpstan-import-type Invalid from int
 */
final class DummyWithTypeAliasImportedFromInvalidClassName
{
}
