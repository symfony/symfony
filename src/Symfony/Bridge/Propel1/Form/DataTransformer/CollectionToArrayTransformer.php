<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Form\DataTransformer;

use Symfony\Bridge\Propel1\Form\ChoiceList\ModelChoiceList;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;

use \PropelCollection;
use \PropelObjectCollection;

/**
 * CollectionToArrayTransformer class.
 *
 * @author William Durand <william.durand1@gmail.com>
 * @author Pierre-Yves Lebecq <py.lebecq@gmail.com>
 */
class CollectionToArrayTransformer implements DataTransformerInterface
{
    /**
     * @var \Symfony\Bridge\Propel1\Form\ChoiceList\ModelChoiceList
     */
    private $choiceList;

    public function __construct(ModelChoiceList $choiceList)
    {
        $this->choiceList = $choiceList;
    }

    public function transform($collection)
    {
        if (null === $collection) {
            return array();
        }

        if (!$collection instanceof PropelCollection) {
            throw new UnexpectedTypeException($collection, '\PropelCollection');
        }

        $collection->setModel($this->choiceList->getClass());

        return $collection->toArray();
    }

    public function reverseTransform($array)
    {
        $collection = new PropelObjectCollection();

        if ('' === $array || null === $array) {
            return $collection;
        }

        if (!is_array($array)) {
            throw new UnexpectedTypeException($array, 'array');
        }

        $collection->setModel($this->choiceList->getClass());
        $collection->fromArray($array);

        return $collection;
    }
}
