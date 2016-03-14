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

use Symfony\Component\OptionsResolver\Exception\AccessException;

/**
 * Validates nested options and merges them with default values.
 *
 * This class is used internally by the OptionsResolver.
 * See {@link OptionsResolver::setNested()}.
 *
 * @author Jules Pietri <jules@heahprod.com>
 *
 * @internal
 */
class NestedOptions extends OptionsResolver
{
    /**
     * The root options instance.
     *
     * @var OptionsResolver
     */
    private $root;

    /**
     * The root option name.
     *
     * @var string
     */
    private $rootName;

    /**
     * This class should only be instantiated from an OptionsResolver.
     *
     * See {@link OptionsResolver::setNested()}.
     *
     * @param string $rootName The root option name
     */
    public function __construct($rootName)
    {
        $this->rootName = $rootName;
    }

    /**
     * Binds the root options.
     *
     * This method should only be called from root OptionsResolver instance.
     * See {@link OptionsResolver::__clone()}.
     *
     * @param OptionsResolver $root The root options
     *
     * @return NestedOptions This instance
     */
    public function setRoot(OptionsResolver $root)
    {
        $this->root = $root;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * This method should only be called while the root option is resolved
     * {@link OptionsResolver::OffsetGet()}
     *
     * @throws AccessException If the root option is not locked
     */
    public function resolve(array $options = array())
    {
        if (!$this->root->isLocked()) {
            throw new AccessException(sprintf('The Nested options of "%s" can only be resolved internally while their root is resolved.', $this->rootName));
        }

        return parent::resolve($options);
    }

    /**
     * {@inheritdoc}
     */
    protected function normalize(\Closure $normalizer, $value)
    {
        // Pass the resolved parent options as third argument.
        return $normalizer($this, $value, $this->root);
    }
}
