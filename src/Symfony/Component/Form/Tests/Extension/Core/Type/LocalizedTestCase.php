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

use Symfony\Component\Intl\Intl;

abstract class LocalizedTestCase extends TypeTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Symfony\Component\Intl\Intl')) {
            $this->markTestSkipped('The "Intl" component is not available');
        }

        if (!Intl::isExtensionLoaded()) {
            $this->markTestSkipped('The "intl" extension is not available');
        }

        Intl::setDataSource(Intl::STUB);

        \Locale::setDefault('en');
    }
}
