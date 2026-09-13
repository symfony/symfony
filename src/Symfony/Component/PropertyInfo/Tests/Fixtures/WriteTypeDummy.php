<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

use Symfony\Component\PropertyInfo\Attribute\WithAccessors;

class WriteTypeDummy
{
    public int $propertyOnly = 0;
    public string $nullableSetter = '';
    public ?string $getterOnly = null;
    public ?int $constructed = null;
    public array $items = [];

    public string $hooked = '' {
        set(?string $value) => $value ?? '';
    }

    public string $virtual {
        get => $this->propertyOnly ? 'yes' : 'no';
    }

    public private(set) string $privateSet = '';
    public protected(set) string $protectedSet = '';
    public readonly string $readonly;

    private ?string $setterWins = null;
    private string $privateSetter = '';
    private string $plainPrivate = '';

    #[WithAccessors(setter: 'rename')]
    private string $named = '';

    public function __construct(int $constructed = 0)
    {
        $this->constructed = $constructed;
        $this->readonly = 'fixed';
    }

    public function setSetterWins(string $setterWins): void
    {
        $this->setterWins = $setterWins;
    }

    public function setNullableSetter(?string $nullableSetter): void
    {
        $this->nullableSetter = $nullableSetter ?? '';
    }

    public function getGetterOnly(): string
    {
        return $this->getterOnly ?? '';
    }

    public function addItem(string $item): void
    {
        $this->items[] = $item;
    }

    public function removeItem(string $item): void
    {
        $this->items = array_diff($this->items, [$item]);
    }

    public function rename(?string $named): void
    {
        $this->named = $named ?? '';
    }

    public function setNamed(int $named): void
    {
        $this->named = (string) $named;
    }

    private function setPrivateSetter(string $privateSetter): void
    {
        $this->privateSetter = $privateSetter;
    }
}
