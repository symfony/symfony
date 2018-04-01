<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Core\Type;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symphony\Component\Form\Extension\Core\Type\ButtonType';

    public function testCreateButtonInstances()
    {
        $this->assertInstanceOf('Symphony\Component\Form\Button', $this->factory->create(static::TESTED_TYPE));
    }
}
