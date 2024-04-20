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

use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;

class CountryTypeValidatorExtensionTest extends BaseValidatorExtensionTestCase
{
    use ValidatorExtensionTrait;

    protected function createForm(array $options = [])
    {
        return $this->factory->create(CountryType::class, null, $options);
    }

    public function testInvalidMessage()
    {
        $form = $this->createForm();

        $this->assertSame('Please select a valid country.', $form->getConfig()->getOption('invalid_message'));
    }
}
