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

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;

class DateTypeValidatorExtensionTest extends BaseValidatorExtensionTestCase
{
    use ValidatorExtensionTrait;

    protected function createForm(array $options = [])
    {
        return $this->factory->create(DateType::class, null, $options + ['widget' => 'choice']);
    }

    public function testInvalidMessage()
    {
        $form = $this->createForm();

        $this->assertSame('Please enter a valid date.', $form->getConfig()->getOption('invalid_message'));
    }
}
