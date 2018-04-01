<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Validator\Type;

use Symphony\Component\Form\Test\Traits\ValidatorExtensionTrait;

class SubmitTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    use ValidatorExtensionTrait;

    protected function createForm(array $options = array())
    {
        return $this->factory->create('Symphony\Component\Form\Extension\Core\Type\SubmitType', null, $options);
    }
}
