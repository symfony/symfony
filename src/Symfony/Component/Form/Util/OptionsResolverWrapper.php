<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class OptionsResolverWrapper extends OptionsResolver
{
    private array $undefined = [];

    /**
     * @return $this
     */
    public function setNormalizer(string $option, \Closure $normalizer): static
    {
        try {
            parent::setNormalizer($option, $normalizer);
        } catch (UndefinedOptionsException) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addNormalizer(string $option, \Closure $normalizer, bool $forcePrepend = false): static
    {
        try {
            parent::addNormalizer($option, $normalizer, $forcePrepend);
        } catch (UndefinedOptionsException) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setAllowedValues(string $option, mixed $allowedValues): static
    {
        try {
            parent::setAllowedValues($option, $allowedValues);
        } catch (UndefinedOptionsException) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addAllowedValues(string $option, mixed $allowedValues): static
    {
        try {
            parent::addAllowedValues($option, $allowedValues);
        } catch (UndefinedOptionsException) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @param string|array $allowedTypes
     *
     * @return $this
     */
    public function setAllowedTypes(string $option, $allowedTypes): static
    {
        try {
            parent::setAllowedTypes($option, $allowedTypes);
        } catch (UndefinedOptionsException) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @param string|array $allowedTypes
     *
     * @return $this
     */
    public function addAllowedTypes(string $option, $allowedTypes): static
    {
        try {
            parent::addAllowedTypes($option, $allowedTypes);
        } catch (UndefinedOptionsException) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setDeprecated(string $option, string $package = '', string $version = '', string|\Closure $message = 'The option "%name%" is deprecated.'): static
    {
        try {
            parent::setDeprecated(...\func_get_args());
        } catch (UndefinedOptionsException) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setInfo(string $option, string $info): static
    {
        try {
            parent::setInfo($option, $info);
        } catch (UndefinedOptionsException) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    public function getInfo(string $option): ?string
    {
        try {
            return parent::getInfo($option);
        } catch (UndefinedOptionsException) {
            return null;
        }
    }

    public function resolve(array $options = []): array
    {
        throw new AccessException('Resolve options is not supported.');
    }

    public function getUndefinedOptions(): array
    {
        return array_keys($this->undefined);
    }
}
