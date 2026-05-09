<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class CollectionWithRecursiveSetDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $event->getForm()->add('foo', TextType::class);
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
            $form = $event->getForm();
            $data = $event->getData();
            if (!\is_array($data) || !$data || $form->has('reentry_marker')) {
                return;
            }
            $form->add('reentry_marker', TextType::class);
            $form->setData($data);
        }, 256);
    }

    public function getParent(): ?string
    {
        return CollectionType::class;
    }
}
