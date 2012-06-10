<?php

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class AuthorWithListenersType extends AbstractType
{
    const 
        POST_SET_DATA = 1,
        POST_BIND = 2
    ;
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $factory = $builder->getFormFactory();
        
        $builder->add('lastName');
        
        $basicListener = function(FormEvent $e) use ($factory) {
            $data = $e->getData();
            if ($data == 'Smith') {
                $e->getForm()->getParent()->add($factory->createNamed('australian', 'checkbox'));
            }
        };
        
        switch($options['listener_set']) {
            case self::POST_SET_DATA:
                $builder->get('lastName')->addEventListener(FormEvents::POST_SET_DATA, $basicListener);
                break;
            case self::POST_BIND:
                $builder->get('lastName')->addEventListener(FormEvents::POST_BIND, $basicListener);
                break;
        }
    }

    public function getName()
    {
        return 'author_with_listeners';
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Symfony\Component\Form\Tests\Fixtures\Author',
            'listener_set' => self::POST_SET_DATA
        ));
        
        $resolver->setAllowedValues(array(
            'listener_set' => array(self::POST_SET_DATA, self::POST_BIND)
        ));
    }
}
