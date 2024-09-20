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

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;

class DateTimeTypeValidatorExtensionTest extends BaseValidatorExtensionTestCase
{
    use ValidatorExtensionTrait;

    protected function createForm(array $options = [])
    {
        return $this->factory->create(DateTimeType::class, null, $options + ['widget' => 'choice']);
    }

    public function testInvalidMessage()
    {
        $form = $this->createForm();

        $this->assertSame('Please enter a valid date and time.', $form->getConfig()->getOption('invalid_message'));
    }
}
