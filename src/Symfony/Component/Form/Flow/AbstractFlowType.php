<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Flow\Type\FormFlowType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
abstract class AbstractFlowType extends AbstractType implements FormFlowTypeInterface
{
    final public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$builder instanceof FormFlowBuilderInterface) {
            throw new \InvalidArgumentException(\sprintf('The "%s" can only be used with FormFlowType.', self::class));
        }

        $this->buildFormFlow($builder, $options);
    }

    final public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$form instanceof FormFlowInterface) {
            throw new \InvalidArgumentException(\sprintf('The "%s" can only be used with FormFlowType.', self::class));
        }

        $this->buildViewFlow($view, $form, $options);
    }

    final public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$form instanceof FormFlowInterface) {
            throw new \InvalidArgumentException(\sprintf('The "%s" can only be used with FormFlowType.', self::class));
        }

        $this->finishViewFlow($view, $form, $options);
    }

    public function buildFormFlow(FormFlowBuilderInterface $builder, array $options): void
    {
    }

    public function buildViewFlow(FormView $view, FormFlowInterface $form, array $options): void
    {
    }

    public function finishViewFlow(FormView $view, FormFlowInterface $form, array $options): void
    {
    }

    public function getParent(): string
    {
        return FormFlowType::class;
    }
}
