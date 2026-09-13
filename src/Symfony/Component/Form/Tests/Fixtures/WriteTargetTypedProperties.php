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

class WriteTargetTypedProperties
{
    public ?int $qty = null;
    public ?string $name = null;
    public string $label = '';

    public string $comment = '' {
        set(?string $value) => $value ?? 'none';
    }

    public string $heading = 'foo';

    public private(set) string $slug = '';

    public protected(set) string $tone = '';

    public readonly string $ref;

    public string $alias {
        get => $this->heading;
    }

    private ?string $title = null;

    private ?string $thing = null;

    public function __construct(int $qty = 0)
    {
        $this->qty = $qty;
        $this->ref = 'bar';
    }

    public function getName(): string
    {
        return $this->name ?? '';
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label ?? '';
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getThing(): ?string
    {
        return $this->thing;
    }

    private function setThing(string $thing): void
    {
        $this->thing = $thing;
    }
}
