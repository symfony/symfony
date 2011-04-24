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
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\EventDispatcher\EventDispatcher;

class FormType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->setAttribute('virtual', $options['virtual'])
            ->setDataMapper(new PropertyPathMapper($options['data_class']));
    }

    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
        $multipart = false;

        foreach ($view as $child) {
            if ($child->get('multipart')) {
                $multipart = true;
                break;
            }
        }

        $view->set('multipart', $multipart);
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'virtual' => false,
            // Errors in forms bubble by default, so that form errors will
            // end up as global errors in the root form
            'error_bubbling' => true,
        );

        if (empty($options['data_class'])) {
            $defaultOptions['empty_data'] = array();
        }

        return $defaultOptions;
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
