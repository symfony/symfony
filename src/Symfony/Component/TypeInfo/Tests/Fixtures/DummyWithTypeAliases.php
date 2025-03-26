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
 * @phpstan-type CustomString = string
 * @phpstan-import-type CustomInt from DummyWithPhpDoc
 * @phpstan-import-type CustomInt from DummyWithPhpDoc as AliasedCustomInt
 *
 * @psalm-type PsalmCustomString = string
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
 * @phpstan-import-type Invalid from DummyWithTypeAliases
 */
final class DummyWithInvalidTypeAliasImport
{
}
