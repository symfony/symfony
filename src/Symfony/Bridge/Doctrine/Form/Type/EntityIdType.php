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

class EntityIdType extends AbstractType
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
            'hidden'            => true
        );

        $options = array_replace($defaultOptions, $options);

        return $defaultOptions;
    }

    public function getParent(array $options)
    {
        return $options['hidden'] ? 'hidden' : 'field';
    }

    public function getName()
    {
        return 'entity_id';
    }
}
