<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Extension\Core\CoreExtension;

/**
 * The default implementation of FormFactoryBuilderInterface.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormFactoryBuilder implements FormFactoryBuilderInterface
{
    private ResolvedFormTypeFactoryInterface $resolvedTypeFactory;

    /**
     * @var FormExtensionInterface[]
     */
    private array $extensions = [];

    /**
     * @var FormTypeInterface[]
     */
    private array $types = [];

    /**
     * @var FormTypeExtensionInterface[][]
     */
    private array $typeExtensions = [];

    /**
     * @var FormTypeGuesserInterface[]
     */
    private array $typeGuessers = [];

    public function __construct(
        private bool $forceCoreExtension = false,
    ) {
    }

    public function setResolvedTypeFactory(ResolvedFormTypeFactoryInterface $resolvedTypeFactory): static
    {
        $this->resolvedTypeFactory = $resolvedTypeFactory;

        return $this;
    }

    public function addExtension(FormExtensionInterface $extension): static
    {
        $this->extensions[] = $extension;

        return $this;
    }

    public function addExtensions(array $extensions): static
    {
        $this->extensions = array_merge($this->extensions, $extensions);

        return $this;
    }

    public function addType(FormTypeInterface $type): static
    {
        $this->types[] = $type;

        return $this;
    }

    public function addTypes(array $types): static
    {
        foreach ($types as $type) {
            $this->types[] = $type;
        }

        return $this;
    }

    public function addTypeExtension(FormTypeExtensionInterface $typeExtension): static
    {
        foreach ($typeExtension::getExtendedTypes() as $extendedType) {
            $this->typeExtensions[$extendedType][] = $typeExtension;
        }

        return $this;
    }

    public function addTypeExtensions(array $typeExtensions): static
    {
        foreach ($typeExtensions as $typeExtension) {
            $this->addTypeExtension($typeExtension);
        }

        return $this;
    }

    public function addTypeGuesser(FormTypeGuesserInterface $typeGuesser): static
    {
        $this->typeGuessers[] = $typeGuesser;

        return $this;
    }

    public function addTypeGuessers(array $typeGuessers): static
    {
        $this->typeGuessers = array_merge($this->typeGuessers, $typeGuessers);

        return $this;
    }

    public function getFormFactory(): FormFactoryInterface
    {
        $extensions = $this->extensions;

        if ($this->forceCoreExtension) {
            $hasCoreExtension = false;

            foreach ($extensions as $extension) {
                if ($extension instanceof CoreExtension) {
                    $hasCoreExtension = true;
                    break;
                }
            }

            if (!$hasCoreExtension) {
                array_unshift($extensions, new CoreExtension());
            }
        }

        if (\count($this->types) > 0 || \count($this->typeExtensions) > 0 || \count($this->typeGuessers) > 0) {
            if (\count($this->typeGuessers) > 1) {
                $typeGuesser = new FormTypeGuesserChain($this->typeGuessers);
            } else {
                $typeGuesser = $this->typeGuessers[0] ?? null;
            }

            $extensions[] = new PreloadedExtension($this->types, $this->typeExtensions, $typeGuesser);
        }

        $registry = new FormRegistry($extensions, $this->resolvedTypeFactory ?? new ResolvedFormTypeFactory());

        return new FormFactory($registry);
    }
}
