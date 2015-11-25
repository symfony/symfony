<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Button;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonTypeTest extends BaseTypeTest
{
    public function testCreateButtonInstances()
    {
        $this->assertInstanceOf(Button::class, $this->factory->create(ButtonType::class));
    }

    protected function getTestedType()
    {
        return ButtonType::class;
    }
}
