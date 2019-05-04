<?php

declare(strict_types=1);

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class TestClassGetValues
{
    private $stringValue;
    private $intValue;
    private $dateTimeValue;
    private $arrayValue;

    public function __construct(string $stringValue, int $intValue, \DateTimeImmutable $dateTimeValue, array $arrayValue)
    {
        $this->stringValue = $stringValue;
        $this->intValue = $intValue;
        $this->dateTimeValue = $dateTimeValue;
        $this->arrayValue = $arrayValue;
    }

    public function getStringValue(): string
    {
        return $this->stringValue;
    }

    public function getIntValue(): int
    {
        return $this->intValue;
    }
    public function getDateTimeValue(): \DateTimeImmutable
    {
        return $this->dateTimeValue;
    }

    public function getArrayValue(): array
    {
        return $this->arrayValue;
    }
}
