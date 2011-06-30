<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Validator\Type;

use Symfony\Tests\Component\Form\Extension\Core\Type\TypeTestCase as BaseTestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;

abstract class TypeTestCase extends BaseTestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->validator = null;

        parent::tearDown();
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), array(
            new ValidatorExtension($this->validator),
        ));
    }
}
