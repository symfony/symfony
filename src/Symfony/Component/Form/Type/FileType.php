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

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\EventListener\FixFileUploadListener;
use Symfony\Component\Form\DataTransformer\DataTransformerChain;
use Symfony\Component\Form\DataTransformer\ReversedTransformer;
use Symfony\Component\Form\DataTransformer\FileToStringTransformer;
use Symfony\Component\Form\DataTransformer\FileToArrayTransformer;
use Symfony\Component\Form\Renderer\FormRendererInterface;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;

class FileType extends AbstractType
{
    private $storage;

    public function __construct(TemporaryStorage $storage)
    {
        $this->storage = $storage;
    }

    public function buildForm(FormBuilder $builder, array $options)
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
            ->add('file', 'field')
            ->add('token', 'hidden')
            ->add('name', 'hidden');
    }

    public function buildRendererBottomUp(FormRendererInterface $renderer, FormInterface $form)
    {
        $renderer->setVar('multipart', true);
        $renderer['file']->setVar('type', 'file');
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'type' => 'string',
            'csrf_protection' => false,
        );
    }

    public function getName()
    {
        return 'file';
    }
}
