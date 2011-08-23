<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\Type;

use Symfony\Component\Form\FormBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bridge\Doctrine\Form\DataTransformer\OneEntityToIdTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\FormException;

class EntityIdentifierType extends AbstractType
{
    protected $registry;

    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->prependClientTransformer(new OneEntityToIdTransformer(
            $this->registry->getEntityManager($options['em']),
            $options['class'], 
            $options['property'],
            $options['query_builder']
        ));
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'required'          => true,
            'em'                => null,
            'class'             => null,
            'query_builder'     => null,
            'property'          => null,
            'hidden'            => true
        );

        $options = array_replace($defaultOptions, $options);

        if (null === $options['class']) {
            throw new FormException('You must provide a class option for the entity_identifier field');
        }  

        return $defaultOptions;
    }

    public function getParent(array $options)
    {
        return $options['hidden'] ? 'hidden' : 'field';
    }

    public function getName()
    {
        return 'entity_identifier';
    }
}
