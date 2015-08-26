<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ChoiceList\Factory;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceListView;

/**
 * Caches the choice lists created by the decorated factory.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CachingFactoryDecorator implements ChoiceListFactoryInterface
{
    /**
     * @var ChoiceListFactoryInterface
     */
    private $decoratedFactory;

    /**
     * @var ChoiceListInterface[]
     */
    private $lists = array();

    /**
     * @var ChoiceListView[]
     */
    private $views = array();

    /**
     * Generates a SHA-256 hash for the given value.
     *
     * Optionally, a namespace string can be passed. Calling this method will
     * the same values, but different namespaces, will return different hashes.
     *
     * @param mixed  $value     The value to hash
     * @param string $namespace Optional. The namespace
     *
     * @return string The SHA-256 hash
     *
     * @internal Should not be used by user-land code.
     */
    public static function generateHash($value, $namespace = '')
    {
        if (is_object($value)) {
            $value = spl_object_hash($value);
        } elseif (is_array($value)) {
            array_walk_recursive($value, function (&$v) {
                if (is_object($v)) {
                    $v = spl_object_hash($v);
                }
            });
        }

        return hash('sha256', $namespace.':'.serialize($value));
    }

    /**
     * Flattens an array into the given output variable.
     *
     * @param array $array  The array to flatten
     * @param array $output The flattened output
     *
     * @internal Should not be used by user-land code
     */
    private static function flatten(array $array, &$output)
    {
        if (null === $output) {
            $output = array();
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                self::flatten($value, $output);
                continue;
            }

            $output[$key] = $value;
        }
    }

    /**
     * Decorates the given factory.
     *
     * @param ChoiceListFactoryInterface $decoratedFactory The decorated factory
     */
    public function __construct(ChoiceListFactoryInterface $decoratedFactory)
    {
        $this->decoratedFactory = $decoratedFactory;
    }

    /**
     * Returns the decorated factory.
     *
     * @return ChoiceListFactoryInterface The decorated factory
     */
    public function getDecoratedFactory()
    {
        return $this->decoratedFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createListFromChoices($choices, $value = null)
    {
        if ($choices instanceof \Traversable) {
            $choices = iterator_to_array($choices);
        }

        // The value is not validated on purpose. The decorated factory may
        // decide which values to accept and which not.

        // We ignore the choice groups for caching. If two choice lists are
        // requested with the same choices, but a different grouping, the same
        // choice list is returned.
        self::flatten($choices, $flatChoices);

        $hash = self::generateHash(array($flatChoices, $value), 'fromChoices');

        if (!isset($this->lists[$hash])) {
            $this->lists[$hash] = $this->decoratedFactory->createListFromChoices($choices, $value);
        }

        return $this->lists[$hash];
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Added for backwards compatibility in Symfony 2.7, to be
     *             removed in Symfony 3.0.
     */
    public function createListFromFlippedChoices($choices, $value = null)
    {
        if ($choices instanceof \Traversable) {
            $choices = iterator_to_array($choices);
        }

        // The value is not validated on purpose. The decorated factory may
        // decide which values to accept and which not.

        // We ignore the choice groups for caching. If two choice lists are
        // requested with the same choices, but a different grouping, the same
        // choice list is returned.
        self::flatten($choices, $flatChoices);

        $hash = self::generateHash(array($flatChoices, $value), 'fromFlippedChoices');

        if (!isset($this->lists[$hash])) {
            $this->lists[$hash] = $this->decoratedFactory->createListFromFlippedChoices($choices, $value);
        }

        return $this->lists[$hash];
    }

    /**
     * {@inheritdoc}
     */
    public function createListFromLoader(ChoiceLoaderInterface $loader, $value = null)
    {
        $hash = self::generateHash(array($loader, $value), 'fromLoader');

        if (!isset($this->lists[$hash])) {
            $this->lists[$hash] = $this->decoratedFactory->createListFromLoader($loader, $value);
        }

        return $this->lists[$hash];
    }

    /**
     * {@inheritdoc}
     */
    public function createView(ChoiceListInterface $list, $preferredChoices = null, $label = null, $index = null, $groupBy = null, $attr = null)
    {
        // The input is not validated on purpose. This way, the decorated
        // factory may decide which input to accept and which not.
        $hash = self::generateHash(array($list, $preferredChoices, $label, $index, $groupBy, $attr));

        if (!isset($this->views[$hash])) {
            $this->views[$hash] = $this->decoratedFactory->createView(
                $list,
                $preferredChoices,
                $label,
                $index,
                $groupBy,
                $attr
            );
        }

        return $this->views[$hash];
    }
}
