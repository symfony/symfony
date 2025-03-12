This template is used for translation message extraction tests
<?php
// @see https://github.com/php-translation/extractor/blob/master/tests/Resources/Php/Symfony/ExplicitLabelType.php

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

// Intentionally not included in FormTypeVisitor constructor argument in PhpAstExtractorTest.php
// To test that the visitor can find FormType extending AbstractType.
class FooType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('foo1', null, [
            'label' => 'label.foo1'
        ]);
    }
}

class ExplicitLabelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $var = "something";
        $builder->add('find1', null, [
            'label' => 'label.find1'
        ]);
        $builder
            ->add('find2', null, array(
                'label' => 'find2'
            ))
            ->add('field_longer_name3', null, [
                'label' => 'FOUND3'
            ])
            ->add('skip1', null, [
                'label' => $var, // shouldn't be picked up
                'somethingelse' => 'skipthis',
            ])
            ->add('skip2', null, [
                'label' => PHP_OS, // constant shouldn't work
            ])
            ->add('skip3', null, [
                'label' // value label, shouldn't be picked up
            ])
            ->add('skip4', null, [
                'label' => 'something '.$var // string+var concatenation, shouldn't be picked up
            ])
        ;

        // add label in variable should be found
        $opts = ['label'=>'label.find4'];
        $builder->add('find4', null, $opts);

        // empty label should be skipped
        $builder->add('skip5', null, ['label'=>'']);

        // collection test
        $builder->add('find5', CollectionType::class, [
            'options' => [
                'label' => 'label.find5',
            ],
        ]);

        // implicit labels should be found
        $builder->add('find6');
        $builder->add('bigger_find7');
        $builder->add('camelFind8');
        $builder->add('skip6'.$var);
        $builder->add('skip7', null, ['label'=>'label.find9']);
    }
}
