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

class RepeatedTypeValidatorExtensionTest extends BaseValidatorExtensionTestCase
{
    use ValidatorExtensionTrait;

    protected function createForm(array $options = [])
    {
        return $this->factory->create(RepeatedType::class, null, $options);
    }

    public function testInvalidMessage()
    {
        $form = $this->createForm();

        $this->assertSame('The values do not match.', $form->getConfig()->getOption('invalid_message'));
    }
}
