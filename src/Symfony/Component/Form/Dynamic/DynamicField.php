<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Dynamic;

/**
 * Used to configure a dynamic field.
 *
 * If ->add() is not called, the field won't be included.
 *
 * @author Ryan Weaver
 * @author Benjamin Georgeault <git@wedgesama.fr>
 */
final class DynamicField
{
    private ?string $type = null;
    private array $options = [];
    private bool $shouldBeAdded = false;

    public function add(?string $type = null, array $options = []): static
    {
        $this->type = $type;
        $this->options = $options;
        $this->shouldBeAdded = true;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function shouldBeAdded(): bool
    {
        return $this->shouldBeAdded;
    }
}
