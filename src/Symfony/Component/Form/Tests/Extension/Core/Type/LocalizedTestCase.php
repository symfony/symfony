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

abstract class LocalizedTestCase extends TypeTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Symfony\Component\Locale\Locale')) {
            $this->markTestSkipped('The "Locale" component is not available');
        }

        if (!extension_loaded('intl')) {
            $this->markTestSkipped('The "intl" extension is not available');
        }
    }
}
