<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Input\File\InputFile;
use Symfony\Component\Console\Input\File\InputFile as AliasedFile;

/**
 * Covers the PHPDoc shapes InputFileType must classify. Members are never called; only their
 * reflection and doc comments matter.
 */
class InputFileTypeFixture
{
    /**
     * @var InputFile[]
     */
    public array $propertyArray;

    /**
     * @param InputFile[] $files
     */
    public function arrayShortName(array $files): void
    {
    }

    /**
     * @param list<InputFile> $files
     */
    public function listGeneric(array $files): void
    {
    }

    /**
     * @param array<int, InputFile> $files
     */
    public function arrayGeneric(array $files): void
    {
    }

    /**
     * @param AliasedFile[] $files
     */
    public function aliasedImport(array $files): void
    {
    }

    /**
     * @param non-empty-list<InputFile> $files
     */
    public function nonEmptyList(array $files): void
    {
    }

    /**
     * @param InputFile[]|null $files
     */
    public function nullableArray(?array $files): void
    {
    }

    /**
     * A member typed `iterable` is not treated as a collection, only `array` is.
     *
     * @param iterable<InputFile> $files
     */
    public function iterableType(iterable $files): void
    {
    }

    public function variadic(InputFile ...$files): void
    {
    }

    public function single(InputFile $file): void
    {
    }

    public function plainArray(array $tags): void
    {
    }

    /**
     * @param string[] $tags
     */
    public function stringArray(array $tags): void
    {
    }

    public function __construct(
        /** @var InputFile[] */
        public array $promoted = [],
    ) {
    }
}
