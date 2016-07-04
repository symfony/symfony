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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonTypeTest extends BaseTypeTest
{
    public function testCreateButtonInstances()
    {
        $this->assertInstanceOf('Symfony\Component\Form\Button', $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ButtonType'));
    }

    protected function getTestedType()
    {
        return 'Symfony\Component\Form\Extension\Core\Type\ButtonType';
    }
}
