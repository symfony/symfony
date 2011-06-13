<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\FileToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\FileToArrayTransformer;
use Symfony\Component\Form\FormView;

class FileType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        if ($options['type'] === 'string') {
            $builder->appendNormTransformer(
                new ReversedTransformer(new FileToStringTransformer())
            );
        }

        $builder
            ->appendNormTransformer(new FileToArrayTransformer())
            ->add('file', 'field')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
        $view
            ->set('multipart', true)
            ->getChild('file')
                ->set('type', 'file')
                ->set('value', '')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'type' => 'string',
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedOptionValues(array $options)
    {
        return array(
            'type' => array(
                'string',
                'file',
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'file';
    }
}
