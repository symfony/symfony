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

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\FormRendererInterface;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\DataMapper\PropertyPathMapper;
use Symfony\Component\EventDispatcher\EventDispatcher;

class FormType extends AbstractType
{
    public function configure(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('virtual', $options['virtual'])
            ->setDataClass($options['data_class'])
            ->setDataMapper(new PropertyPathMapper(
                $options['data_class'],
                $options['data_constructor']
            ));

        if ($options['csrf_protection']) {
            $csrfOptions = array('page_id' => $options['csrf_page_id']);

            if ($options['csrf_provider']) {
                $csrfOptions['csrf_provider'] = $options['csrf_provider'];
            }

            $builder->add($options['csrf_field_name'], 'csrf', $csrfOptions);
        }
    }

    public function buildRenderer(FormRendererInterface $renderer, FormInterface $form)
    {
        $renderer->setBlock('form');
        $renderer->setVar('multipart', function () use ($renderer) {
            foreach ($renderer as $child) {
                if ($child->getVar('multipart')) {
                    return true;
                }
            }

            return false;
        });
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'form',
            'data_class' => null,
            'data_constructor' => null,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_provider' => null,
            'csrf_page_id' => get_class($this),
            'validation_groups' => null,
            'virtual' => false,
            // Errors in forms bubble by default, so that form errors will
            // end up as global errors in the root form
            'error_bubbling' => true,
        );
    }

    public function getParent(array $options)
    {
        return 'field';
    }

    public function getName()
    {
        return 'form';
    }
}