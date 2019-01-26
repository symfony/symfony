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

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;

class RepeatedTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    use ValidatorExtensionTrait;

    protected function createForm(array $options = array())
    {
        return $this->factory->create(RepeatedType::class, null, $options);
    }

    public function testInvalidMessage()
    {
        $form = $this->createForm();

        $this->assertSame('The repeated value is invalid.', $form->getConfig()->getOption('invalid_message'));
    }

    /**
     * @group legacy
     * @expectedDeprecation Setting the option 'legacy_error_messages' to 'true' is deprecated and will be disabled by default in Symfony 5.0
     */
    public function testLegacyInvalidMessage()
    {
        $form = $this->createForm(array(
            'legacy_error_messages' => true,
        ));

        $this->assertSame('This value is not valid.', $form->getConfig()->getOption('invalid_message'));
    }
}
