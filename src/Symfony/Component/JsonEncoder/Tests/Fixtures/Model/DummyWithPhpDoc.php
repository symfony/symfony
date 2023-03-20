<?php

namespace Symfony\Component\JsonEncoder\Tests\Fixtures\Model;

class DummyWithPhpDoc
{
    /**
     * @var array<DummyWithNameAttributes>
     */
    public mixed $arrayOfDummies = [];

    public array $array = [];

    /**
     * @param array<DummyWithNameAttributes> $arrayOfDummies
     *
     * @return array<string>
     */
    public static function castArrayOfDummiesToArrayOfStrings(mixed $arrayOfDummies): mixed
    {
        return array_column('name', $arrayOfDummies);
    }

    public static function countArray(array $array): int
    {
        return count($array);
    }
}
