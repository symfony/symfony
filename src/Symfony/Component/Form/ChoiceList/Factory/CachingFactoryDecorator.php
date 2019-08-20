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
use Symfony\Contracts\Service\ResetInterface;

/**
 * Caches the choice lists created by the decorated factory.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CachingFactoryDecorator implements ChoiceListFactoryInterface, ResetInterface
{
    private $decoratedFactory;

    /**
     * @var ChoiceListInterface[]
     */
    private $lists = [];

    /**
     * @var ChoiceListView[]
     */
    private $views = [];

    /**
     * Generates a SHA-256 hash for the given value.
     *
     * Optionally, a namespace string can be passed. Calling this method will
     * the same values, but different namespaces, will return different hashes.
     *
     * @param mixed $value The value to hash
     *
     * @return string The SHA-256 hash
     *
     * @internal
     */
    public static function generateHash($value, string $namespace = ''): string
    {
        if (\is_object($value)) {
            $value = spl_object_hash($value);
        } elseif (\is_array($value)) {
            array_walk_recursive($value, function (&$v) {
                if (\is_object($v)) {
                    $v = spl_object_hash($v);
                }
            });
        }

        return hash('sha256', $namespace.':'.serialize($value));
    }

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
    public function createListFromChoices(iterable $choices, $value = null)
    {
        if ($choices instanceof \Traversable) {
            $choices = iterator_to_array($choices);
        }

        // The value is not validated on purpose. The decorated factory may
        // decide which values to accept and which not.

        $hash = self::generateHash([$choices, $value], 'fromChoices');

        if (!isset($this->lists[$hash])) {
            $this->lists[$hash] = $this->decoratedFactory->createListFromChoices($choices, $value);
        }

        return $this->lists[$hash];
    }

    /**
     * {@inheritdoc}
     */
    public function createListFromLoader(ChoiceLoaderInterface $loader, $value = null)
    {
        $hash = self::generateHash([$loader, $value], 'fromLoader');

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
        $hash = self::generateHash([$list, $preferredChoices, $label, $index, $groupBy, $attr]);

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

    public function reset()
    {
        $this->lists = [];
        $this->views = [];
    }
}
