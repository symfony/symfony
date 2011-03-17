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
use Symfony\Component\Form\Config\Loader\DefaultConfigLoader;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected $theme;

    protected $csrfProvider;

    protected $validator;

    protected $storage;

    private $em;

    protected $factory;

    protected function setUp()
    {
        $this->theme = $this->getMock('Symfony\Component\Form\Renderer\Theme\ThemeInterface');
        $this->csrfProvider = $this->getMock('Symfony\Component\Form\CsrfProvider\CsrfProviderInterface');
        $this->validator = $this->getMock('Symfony\Component\Validator\ValidatorInterface');

        $this->storage = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\TemporaryStorage')
            ->disableOriginalConstructor()
            ->getMock();
        $this->em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $loader = new DefaultConfigLoader();
        $this->factory = new FormFactory($loader);
        $loader->initialize($this->factory, $this->theme, $this->csrfProvider,
                $this->validator, $this->storage, $this->em);
    }
}