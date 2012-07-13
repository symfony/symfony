<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\Extension\Core\CoreExtension;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormIntegrationTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FormRegistry
     */
    protected $registry;

    /**
     * @var FormFactory
     */
    protected $factory;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        $this->registry = new FormRegistry($this->getExtensions());
        $this->factory = new FormFactory($this->registry);
    }

    protected function getExtensions()
    {
        return array(
            new CoreExtension(),
        );
    }
}
