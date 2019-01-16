<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator\Type;

use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;

class SubmitTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    use ValidatorExtensionTrait;

    protected function createForm(array $options = [])
    {
        return $this->factory->create('Symfony\Component\Form\Extension\Core\Type\SubmitType', null, $options);
    }
}
