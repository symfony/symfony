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

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Form\FormBuilder;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeCollectionListener;
use Symfony\Bridge\Doctrine\Form\DataTransformer\EntitiesToArrayTransformer;
use Symfony\Bridge\Doctrine\Form\DataTransformer\EntityToIdTransformer;
use Symfony\Bridge\Doctrine\Form\DataTransformer\EntityToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\FormException;

abstract class DoctrineType extends AbstractType
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function buildForm(FormBuilder $builder, array $options)
    {
        if($options['widget'] === 'choice') {
            if ($options['multiple']) {
                $builder
                    ->addEventSubscriber(new MergeCollectionListener())
                    ->prependClientTransformer(new EntitiesToArrayTransformer($options['choice_list']))
                ;
            } else {
                $builder->prependClientTransformer(new EntityToIdTransformer($options['choice_list']));
            }
        } else {
            if($options['multiple']) {
                throw new FormException(sprintf('Using multiple entities is currently only supported with the widget "choice".'));
            }
            
            $propertyOptions = $identifierOptions = array();
            
            foreach (array('required', 'translation_domain') as $passOpt) {
                $propertyOptions[$passOpt] = $identifierOptions[$passOpt] = $options[$passOpt];
            }
            
            if($options['property']) {
                $builder->add($options['property'], 'text', $propertyOptions);
            }
            
            $builder
                ->add('id', $options['widget'], $identifierOptions)
                ->prependClientTransformer(new EntityToArrayTransformer(
                    $this->registry->getManager($options['em']), 
                    $options['class'],
                    $options['property'],
                    $options['query_builder']
                ))
            ;            
        }
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'em'                => null,
            'class'             => null,
            'property'          => null,
            'query_builder'     => null,
            'loader'            => null,
            'choices'           => null,
            'group_by'          => null,
            'widget'            => 'choice',
            'preferred_choices' => array(),
            'multiple'          => false,
        );

        $options = array_replace($defaultOptions, $options);

        if (!isset($options['choice_list']) && $options['widget'] === 'choice') {
            $manager = $this->registry->getManager($options['em']);
            if (isset($options['query_builder'])) {
                $options['loader'] = $this->getLoader($manager, $options);
            }

            $defaultOptions['choice_list'] = new EntityChoiceList(
                $manager,
                $options['class'],
                $options['property'],
                $options['loader'],
                $options['choices'],
                $options['group_by']
            );
        }

        return $defaultOptions;
    }

    /**
     * Return the default loader object.
     *
     * @param ObjectManager $manager
     * @param array $options
     * @return EntityLoaderInterface
     */
    abstract protected function getLoader(ObjectManager $manager, array $options);

    public function getParent(array $options)
    {
        return $options['widget'] === 'choice' ? 'choice' : 'form';
    }
}
