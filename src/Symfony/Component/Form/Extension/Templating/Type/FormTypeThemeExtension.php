<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Templating\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRendererEngineInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Allows to define from theme as form type option
 * The form them is passed to rendering engine when Form::createView() is called.
 *
 * @author Damian Wróblewski <damianwroblewski75@gmail.com>
 */
class FormTypeThemeExtension extends AbstractTypeExtension
{
    /**
     * @var FormRendererEngineInterface
     */
    private $rendererEngine;

    /**
     * @var string
     */
    private $extension;

    public function __construct(FormRendererEngineInterface $rendererEngine, $extension)
    {
        $this->extension = $extension;
        $this->rendererEngine = $rendererEngine;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('theme', null)
            ->setDefault('theme_auto_extension', false);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['theme']) {
            $theme = $options['theme'];
            if ($options['theme_auto_extension']) {
                $theme .= $this->extension;
            }
            $this->rendererEngine->setTheme($view, $theme);
        }
    }

    public function getExtendedType()
    {
        return FormType::class;
    }
}
