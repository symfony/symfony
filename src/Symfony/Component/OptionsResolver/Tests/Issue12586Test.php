<?php

namespace Symfony\Component\OptionsResolver\Tests;

use Symfony\Component\OptionsResolver\OptionsResolver;

class Issue12586Test extends \PHPUnit_Framework_TestCase
{
    public function testBooleanIsAliasedAsBool()
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(array(
            'force' => false,
        ));

        $resolver->setAllowedTypes(array(
            'force' => 'boolean',
        ));

        $resolver->resolve(array(
            'force' => true,
        ));
    }
}
