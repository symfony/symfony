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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class FormIntegrationTestCase extends TestCase
{
    protected FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = Forms::createFormFactoryBuilder()
            ->addExtensions($this->getExtensions())
            ->addTypeExtensions($this->getTypeExtensions())
            ->addTypes($this->getTypes())
            ->addTypeGuessers($this->getTypeGuessers())
            ->getFormFactory();
    }

    /**
     * @return FormExtensionInterface[]
     */
    protected function getExtensions()
    {
        return [];
    }

    /**
     * @return FormTypeExtensionInterface[]
     */
    protected function getTypeExtensions()
    {
        return [];
    }

    /**
     * @return FormTypeInterface[]
     */
    protected function getTypes()
    {
        return [];
    }

    /**
     * @return FormTypeGuesserInterface[]
     */
    protected function getTypeGuessers()
    {
        return [];
    }
}
