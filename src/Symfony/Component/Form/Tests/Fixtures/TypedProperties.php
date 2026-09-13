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

class TypedProperties
{
    public string $name = '';
    public ?string $nickname = null;
    public int $age = 0;
    public float $height = 0.0;
    public bool $active = false;
    public array $tags = [];
    public mixed $extra = null;
    public string|int $identifier = '';

    private string $slug = '';

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug ?? '';
    }
}
