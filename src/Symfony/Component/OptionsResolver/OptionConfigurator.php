<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver;

/**
 * Creates Options for the OptionResolver
 *
 * @author Simon D. Mueller <simon.d.mueller@gmail.com>
 */
final class OptionConfigurator
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var OptionsResolver
     */
    private $resolver;

    /**
     * OptionConfigurator constructor.
     */
    public function __construct(string $name, OptionsResolver $resolver)
    {
        $this->name = $name;
        $this->resolver = $resolver;
        $this->resolver->setDefined($name);
    }

    /**
     * @param $value
     *
     * @return OptionConfigurator
     */
    public function default($value): self
    {
        $this->resolver->setDefault($this->name, $value);

        return $this;
    }

    /**
     * @return OptionConfigurator
     */
    public function required(): self
    {
        $this->resolver->setRequired($this->name);

        return $this;
    }

    /**
     * @param $message
     *
     * @return OptionConfigurator
     */
    public function deprecated($message): self
    {
        $this->resolver->setDeprecated($this->name, $message);

        return $this;
    }

    /**
     * @param $type
     *
     * @return OptionConfigurator
     */
    public function allowedTypes($type): self
    {
        $this->resolver->setAllowedTypes($this->name, $type);

        return $this;
    }

    /**
     * @param $values
     *
     * @return OptionConfigurator
     */
    public function allowedValues($values): self
    {
        $this->resolver->setAllowedValues($this->name, $values);

        return $this;
    }

    /**
     * @return OptionConfigurator
     */
    public function normalize(callable $normalizer): self
    {
        $this->resolver->setNormalizer($this->name, $normalizer);

        return $this;
    }
}
