<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Theme;

use Symfony\Component\Form\Exception\FormException;

/**
 * Creates TwigTheme instances
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class TwigThemeFactory implements FormThemeFactoryInterface
{
    /**
     * @var Twig_Environment
     */
    private $environment;

    public function __construct(\Twig_Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * @see Symfony\Component\Form\Renderer\Theme\FormThemeFactoryInterface::create()
     */
    public function create($template = null)
    {
        if ($template === null) {
            throw new FormException('Twig themes expect a template');
        }

        return new TwigTheme($this->environment, $template);
    }
}