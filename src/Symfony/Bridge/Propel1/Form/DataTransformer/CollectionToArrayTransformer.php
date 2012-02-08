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
     * @var \Propel\PropelBundle\Form\ChoiceList\ModelChoiceList
     */
    private $choiceList;

    /**
     * @param \Propel\PropelBundle\Form\ChoiceList\ModelChoiceList $choiceList
     */
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

        $array = array();

        if (count($this->choiceList->getIdentifier()) > 1) {
            $availableModels = $this->choiceList->getModels();

            foreach ($collection as $model) {
                $key = array_search($model, $availableModels);
                $array[] = $key;
            }
        } else {
            foreach ($collection as $model) {
                $array[] = current($this->choiceList->getIdentifierValues($model));
            }
        }

        return $array;
    }

    public function reverseTransform($keys)
    {
        $collection = new PropelObjectCollection();

        if ('' === $keys || null === $keys) {
            return $collection;
        }

        if (!is_array($keys)) {
            throw new UnexpectedTypeException($keys, 'array');
        }

        $notFound = array();

        foreach ($keys as $key) {
            if ($model = $this->choiceList->getModel($key)) {
                $collection->append($model);
            } else {
                $notFound[] = $key;
            }
        }

        if (count($notFound) > 0) {
            throw new TransformationFailedException(sprintf('The models with keys "%s" could not be found', implode('", "', $notFound)));
        }

        return $collection;
    }
}
