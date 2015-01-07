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

class SubmitTypeValidatorExtensionTest extends BaseValidatorExtensionTest
{
    protected function createForm(array $options = array())
    {
        return $this->factory->create('submit', $options);
    }
}
