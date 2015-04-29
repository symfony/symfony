<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Test;

use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class FormIntegrationTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FormFactoryInterface
     */
    protected $factory;

    protected function setUp()
    {
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtensions($this->getExtensions())
            ->addTypeExtensions($this->getTypeExtensions())
            ->addTypeGuessers($this->getTypeGuessers())
            ->getFormFactory();
    }

    protected function getExtensions()
    {
        return array();
    }

    protected function getTypeExtensions()
    {
        return array();
    }

    protected function getTypeGuessers()
    {
        return array();
    }
}
