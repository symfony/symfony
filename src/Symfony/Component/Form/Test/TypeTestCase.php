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

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

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

        $this->dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->builder = new FormBuilder(null, null, $this->dispatcher, $this->factory);
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
