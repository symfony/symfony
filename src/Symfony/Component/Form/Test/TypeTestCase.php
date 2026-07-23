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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormExtensionInterface;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;

abstract class TypeTestCase extends FormIntegrationTestCase
{
    protected FormBuilder $builder;
    protected EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset($this->dispatcher)) {
            $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        }
        $this->builder = new FormBuilder('', null, $this->dispatcher, $this->factory);
    }

    /**
     * @param ViolationMapperInterface|null $violationMapper
     *
     * @return FormExtensionInterface[]
     */
    protected function getExtensions(/* ?ViolationMapperInterface $violationMapper = null */): array
    {
        $violationMapper = \func_num_args() ? func_get_arg(0) : null;
        $extensions = [];

        if (\in_array(ValidatorExtensionTrait::class, class_uses($this), true)) {
            $extensions[] = $this->getValidatorExtension($violationMapper);
        }

        return $extensions;
    }

    public static function assertDateTimeEquals(\DateTime $expected, \DateTime $actual): void
    {
        self::assertEquals($expected->format('c'), $actual->format('c'));
    }

    public static function assertDateIntervalEquals(\DateInterval $expected, \DateInterval $actual): void
    {
        self::assertEquals($expected->format('%RP%yY%mM%dDT%hH%iM%sS'), $actual->format('%RP%yY%mM%dDT%hH%iM%sS'));
    }
}
