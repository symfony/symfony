<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\EventListener\FixFileUploadListener;
use Symfony\Component\Form\DataTransformer\DataTransformerChain;
use Symfony\Component\Form\DataTransformer\ReversedTransformer;
use Symfony\Component\Form\DataTransformer\FileToStringTransformer;
use Symfony\Component\Form\DataTransformer\FileToArrayTransformer;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;

class FileFieldType extends AbstractFieldType
{
    private $storage;

    public function __construct(TemporaryStorage $storage)
    {
        $this->storage = $storage;
    }

    public function configure(FieldBuilder $builder, array $options)
    {
        if ($options['type'] === 'string') {
            $builder->setNormTransformer(new DataTransformerChain(array(
                new ReversedTransformer(new FileToStringTransformer()),
                new FileToArrayTransformer(),
            )));
        } else {
            $builder->setNormTransformer(new FileToArrayTransformer());
        }

        $builder->addEventSubscriber(new FixFileUploadListener($this->storage), 10)
            ->add('field', 'file')
            ->add('hidden', 'token')
            ->add('hidden', 'name');

        $builder->get('file')->setRendererVar('type', 'file');
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'file',
            'type' => 'string',
            'csrf_protection' => false,
        );
    }

    public function getParent(array $options)
    {
        return 'form';
    }

    public function getName()
    {
        return 'file';
    }
}