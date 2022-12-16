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

use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;

class RepeatedTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    use ValidatorExtensionTrait;

    protected function createForm(array $options = [])
    {
        return $this->factory->create(RepeatedType::class, null, [
            'first_options' => ['empty_data' => null],
            'second_options' => ['empty_data' => null],
        ] + $options);
    }

    public function testInvalidMessage()
    {
        $form = $this->createForm();

        $this->assertSame('The values do not match.', $form->getConfig()->getOption('invalid_message'));
    }
}
