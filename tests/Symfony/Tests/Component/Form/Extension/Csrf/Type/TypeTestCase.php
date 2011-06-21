<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Csrf\Type;

use Symfony\Tests\Component\Form\Extension\Core\Type\TypeTestCase as BaseTestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;

abstract class TypeTestCase extends BaseTestCase
{
    protected $csrfProvider;

    protected function setUp()
    {
        $this->csrfProvider = $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface');

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->csrfProvider = null;

        parent::tearDown();
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), array(
            new CsrfExtension($this->csrfProvider),
        ));
    }
}
