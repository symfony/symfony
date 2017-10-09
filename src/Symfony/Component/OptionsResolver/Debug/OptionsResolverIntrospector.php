<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver\Debug;

use Symfony\Component\OptionsResolver\Exception\NoConfigurationException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @final
 */
class OptionsResolverIntrospector
{
    private $get;

    public function __construct(OptionsResolver $optionsResolver)
    {
        $this->get = \Closure::bind(function ($property, $option, $message) {
            /** @var OptionsResolver $this */
            if (!$this->isDefined($option)) {
                throw new UndefinedOptionsException(sprintf('The option "%s" does not exist.', $option));
            }

            if (!array_key_exists($option, $this->{$property})) {
                throw new NoConfigurationException($message);
            }

            return $this->{$property}[$option];
        }, $optionsResolver, $optionsResolver);
    }

    /**
     * @param string $option
     *
     * @return mixed
     *
     * @throws NoConfigurationException on no configured value
     */
    public function getDefault($option)
    {
        return call_user_func($this->get, 'defaults', $option, sprintf('No default value was set for the "%s" option.', $option));
    }

    /**
     * @param string $option
     *
     * @return \Closure[]
     *
     * @throws NoConfigurationException on no configured closures
     */
    public function getLazyClosures($option)
    {
        return call_user_func($this->get, 'lazy', $option, sprintf('No lazy closures were set for the "%s" option.', $option));
    }

    /**
     * @param string $option
     *
     * @return string[]
     *
     * @throws NoConfigurationException on no configured types
     */
    public function getAllowedTypes($option)
    {
        return call_user_func($this->get, 'allowedTypes', $option, sprintf('No allowed types were set for the "%s" option.', $option));
    }

    /**
     * @param string $option
     *
     * @return mixed[]
     *
     * @throws NoConfigurationException on no configured values
     */
    public function getAllowedValues($option)
    {
        return call_user_func($this->get, 'allowedValues', $option, sprintf('No allowed values were set for the "%s" option.', $option));
    }

    /**
     * @param string $option
     *
     * @return \Closure
     *
     * @throws NoConfigurationException on no configured normalizer
     */
    public function getNormalizer($option)
    {
        return call_user_func($this->get, 'normalizers', $option, sprintf('No normalizer was set for the "%s" option.', $option));
    }
}
