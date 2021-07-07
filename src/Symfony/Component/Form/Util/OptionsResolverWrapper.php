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
    private $undefined = [];

    /**
     * @return $this
     */
    public function setNormalizer($option, \Closure $normalizer): self
    {
        try {
            parent::setNormalizer($option, $normalizer);
        } catch (UndefinedOptionsException $e) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setAllowedValues($option, $allowedValues): self
    {
        try {
            parent::setAllowedValues($option, $allowedValues);
        } catch (UndefinedOptionsException $e) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addAllowedValues($option, $allowedValues): self
    {
        try {
            parent::addAllowedValues($option, $allowedValues);
        } catch (UndefinedOptionsException $e) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @param string|array $allowedTypes
     *
     * @return $this
     */
    public function setAllowedTypes($option, $allowedTypes): self
    {
        try {
            parent::setAllowedTypes($option, $allowedTypes);
        } catch (UndefinedOptionsException $e) {
            $this->undefined[$option] = true;
        }

        return $this;
    }

    /**
     * @param string|array $allowedTypes
     *
     * @return $this
     */
    public function addAllowedTypes($option, $allowedTypes): self
    {
        try {
            parent::addAllowedTypes($option, $allowedTypes);
        } catch (UndefinedOptionsException $e) {
            $this->undefined[$option] = true;
        }

        return $this;
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
