<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Test;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\Forms;
use Symphony\Component\Form\FormFactoryInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class FormIntegrationTestCase extends TestCase
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
            ->addTypes($this->getTypes())
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

    protected function getTypes()
    {
        return array();
    }

    protected function getTypeGuessers()
    {
        return array();
    }
}
