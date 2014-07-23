<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\AutoLabel\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Extension for automated label generation.
 *
 * @since  2.7
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class AutoLabelTypeExtension extends AbstractTypeExtension
{
    /**
     * @var string
     */
    private $autoLabel;

    /**
     * Constructs a new type extension.
     *
     * The argument "autoLabel" can have placeholders:
     *
     * - %type%    : the form type name (ex: text, choice, date)
     * - %name%    : the name of the form (ex: firstname)
     * - %fullname%: the full name of the form (ex: user_firstname)
     *
     * @param string $autoLabel a default label for forms
     */
    public function __construct($autoLabel = 'form.%type%.label.%name%')
    {
        $this->autoLabel = $autoLabel;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['auto_label'] !== null && $options['label'] === null) {
            $fullname = $form->getName();
            $parent   = $form->getParent();
            while ($parent) {
                $fullname = $parent->getName().'_'.$fullname;
                $parent   = $parent->getParent();
            }

            $view->vars['label'] = strtr($options['auto_label'], array(
                '%name%'     => $form->getName(),
                '%fullname%' => $fullname,
                '%type%'     => $form->getConfig()->getType()->getName(),
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'auto_label' => $this->autoLabel
        ));
    }
}
