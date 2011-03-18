<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config;

use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\EventListener\FixFileUploadListener;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;
use Symfony\Component\Form\ValueTransformer\ReversedTransformer;
use Symfony\Component\Form\ValueTransformer\FileToStringTransformer;
use Symfony\Component\Form\ValueTransformer\FileToArrayTransformer;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;

class FileFieldConfig extends AbstractFieldConfig
{
    private $storage;

    public function __construct(TemporaryStorage $storage)
    {
        $this->storage = $storage;
    }

    public function configure(FieldBuilder $builder, array $options)
    {
        if ($options['type'] === 'string') {
            $builder->setNormalizationTransformer(new ValueTransformerChain(array(
                new ReversedTransformer(new FileToStringTransformer()),
                new FileToArrayTransformer(),
            )));
        } else {
            $builder->setNormalizationTransformer(new FileToArrayTransformer());
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

    public function getIdentifier()
    {
        return 'file';
    }
}