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

        $this->assertEquals('mytest', $type->getName());
    }

    public function testGetNameWithTypeSuffix()
    {
        $type = new MyTestType();

        $this->assertEquals('mytest', $type->getName());
    }

    public function testGetNameWithFormSuffix()
    {
        $type = new MyTestForm();

        $this->assertEquals('mytest', $type->getName());
    }

    public function testGetNameWithFormTypeSuffix()
    {
        $type = new MyTestFormType();

        $this->assertEquals('mytest', $type->getName());
    }
}

class MyTest extends AbstractType {}

class MyTestType extends AbstractType {}

class MyTestForm extends AbstractType {}

class MyTestFormType extends AbstractType {}
