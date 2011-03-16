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

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Filter\FileUploadFilter;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;
use Symfony\Component\Form\ValueTransformer\ReversedTransformer;
use Symfony\Component\Form\ValueTransformer\FileToStringTransformer;
use Symfony\Component\Form\ValueTransformer\FileToArrayTransformer;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;

class FileFieldConfig extends AbstractFieldConfig
{
    private $storage;

    public function __construct(FormFactoryInterface $factory,
            TemporaryStorage $storage)
    {
        parent::__construct($factory);

        $this->storage = $storage;
    }

    public function configure(FieldInterface $field, array $options)
    {
        if ($options['type'] === 'string') {
            $field->setNormalizationTransformer(new ValueTransformerChain(array(
                new ReversedTransformer(new FileToStringTransformer()),
                new FileToArrayTransformer(),
            )));
        } else {
            $field->setNormalizationTransformer(new FileToArrayTransformer());
        }

        $field->prependFilter(new FileUploadFilter($field, $this->storage))
            ->add($this->getInstance('field', 'file')->setRendererVar('type', 'file'))
            ->add($this->getInstance('hidden', 'token'))
            ->add($this->getInstance('hidden', 'name'))
            // TODO remove this again
            ->setData(null);
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