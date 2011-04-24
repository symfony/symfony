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
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\EventListener\FixFileUploadListener;
use Symfony\Component\Form\Extension\Core\DataTransformer\DataTransformerChain;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\FileToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\FileToArrayTransformer;
use Symfony\Component\Form\FormView;
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
            $builder->appendNormTransformer(
                new ReversedTransformer(new FileToStringTransformer())
            );
        }

        $builder
            ->appendNormTransformer(new FileToArrayTransformer())
            ->addEventSubscriber(new FixFileUploadListener($this->storage), 10)
            ->add('file', 'field')
            ->add('token', 'hidden')
            ->add('name', 'hidden');
    }

    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
        $view->set('multipart', true);
        $view['file']->set('type', 'file');
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
