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

use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;

class TestExtension implements FormExtensionInterface
{
    private $types = [];

    private $extensions = [];

    private $guesser;

    public function __construct(FormTypeGuesserInterface $guesser)
    {
        $this->guesser = $guesser;
    }

    public function addType(FormTypeInterface $type)
    {
        $this->types[$type::class] = $type;
    }

    public function getType($name): FormTypeInterface
    {
        return $this->types[$name] ?? null;
    }

    public function hasType($name): bool
    {
        return isset($this->types[$name]);
    }

    public function addTypeExtension(FormTypeExtensionInterface $extension)
    {
        foreach ($extension::getExtendedTypes() as $type) {
            if (!isset($this->extensions[$type])) {
                $this->extensions[$type] = [];
            }

            $this->extensions[$type][] = $extension;
        }
    }

    public function getTypeExtensions($name): array
    {
        return $this->extensions[$name] ?? [];
    }

    public function hasTypeExtensions($name): bool
    {
        return isset($this->extensions[$name]);
    }

    public function getTypeGuesser(): ?FormTypeGuesserInterface
    {
        return $this->guesser;
    }
}
