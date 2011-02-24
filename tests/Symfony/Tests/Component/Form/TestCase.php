<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\FormFactory;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected $theme;

    protected $csrfProvider;

    protected $validator;

    protected $fieldFactory;

    protected $storage;

    protected $factory;

    protected function setUp()
    {
        $this->theme = $this->getMock('Symfony\Component\Form\Renderer\Theme\ThemeInterface');
        $this->csrfProvider = $this->getMock('Symfony\Component\Form\CsrfProvider\CsrfProviderInterface');
        $this->validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');
        $this->fieldFactory = $this->getMock('Symfony\Component\Form\FieldFactory\FieldFactoryInterface');
        $this->storage = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\TemporaryStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new FormFactory($this->theme, $this->csrfProvider,
                $this->validator, $this->fieldFactory, $this->storage);
    }
}