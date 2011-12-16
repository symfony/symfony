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

/**
 * @author William Durand <william.durand1@gmail.com>
 */
class ModelToIdTransformer implements DataTransformerInterface
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

    public function transform($model)
    {
        if (null === $model || '' === $model) {
            return '';
        }

        if (!is_object($model)) {
            throw new UnexpectedTypeException($model, 'object');
        }

        if (count($this->choiceList->getIdentifier()) > 1) {
            $availableModels = $this->choiceList->getModels();

            return array_search($model, $availableModels);
        }

        return current($this->choiceList->getIdentifierValues($model));
    }

    public function reverseTransform($key)
    {
        if ('' === $key || null === $key) {
            return null;
        }

        if (count($this->choiceList->getIdentifier()) > 1 && !is_numeric($key)) {
            throw new UnexpectedTypeException($key, 'numeric');
        }

        if (!$model = $this->choiceList->getModel($key)) {
            throw new TransformationFailedException(sprintf('The model with key "%s" could not be found', $key));
        }

        return $model;
    }
}
