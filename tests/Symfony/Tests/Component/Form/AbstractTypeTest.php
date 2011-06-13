<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\AbstractType;

class AbstractTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testGetNameWithNoSuffix()
    {
        $type = new MyTest();

        $this->assertEquals('symfony_tests_component_form_mytest', $type->getName());
    }

    public function testGetNameWithTypeSuffix()
    {
        $type = new MyTestType();

        $this->assertEquals('symfony_tests_component_form_mytesttype', $type->getName());
    }

    public function testGetNameWithFormSuffix()
    {
        $type = new MyTestForm();

        $this->assertEquals('symfony_tests_component_form_mytestform', $type->getName());
    }

    public function testGetNameWithFormTypeSuffix()
    {
        $type = new MyTestFormType();

        $this->assertEquals('symfony_tests_component_form_mytestformtype', $type->getName());
    }
}

class MyTest extends AbstractType {}

class MyTestType extends AbstractType {}

class MyTestForm extends AbstractType {}

class MyTestFormType extends AbstractType {}
