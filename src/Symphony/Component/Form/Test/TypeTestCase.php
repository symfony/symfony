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

use Symphony\Component\Form\FormBuilder;
use Symphony\Component\EventDispatcher\EventDispatcher;
use Symphony\Component\Form\Test\Traits\ValidatorExtensionTrait;

abstract class TypeTestCase extends FormIntegrationTestCase
{
    /**
     * @var FormBuilder
     */
    protected $builder;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    protected function setUp()
    {
        parent::setUp();

        $this->dispatcher = $this->getMockBuilder('Symphony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->builder = new FormBuilder(null, null, $this->dispatcher, $this->factory);
    }

    protected function tearDown()
    {
        if (in_array(ValidatorExtensionTrait::class, class_uses($this))) {
            $this->validator = null;
        }
    }

    protected function getExtensions()
    {
        $extensions = array();

        if (in_array(ValidatorExtensionTrait::class, class_uses($this))) {
            $extensions[] = $this->getValidatorExtension();
        }

        return $extensions;
    }

    public static function assertDateTimeEquals(\DateTime $expected, \DateTime $actual)
    {
        self::assertEquals($expected->format('c'), $actual->format('c'));
    }

    public static function assertDateIntervalEquals(\DateInterval $expected, \DateInterval $actual)
    {
        self::assertEquals($expected->format('%RP%yY%mM%dDT%hH%iM%sS'), $actual->format('%RP%yY%mM%dDT%hH%iM%sS'));
    }
}
