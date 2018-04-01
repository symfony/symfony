<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Templating;

use Symphony\Component\Form\AbstractExtension;
use Symphony\Component\Form\FormRenderer;
use Symphony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symphony\Component\Templating\PhpEngine;
use Symphony\Bundle\FrameworkBundle\Templating\Helper\FormHelper;

/**
 * Integrates the Templating component with the Form library.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TemplatingExtension extends AbstractExtension
{
    public function __construct(PhpEngine $engine, CsrfTokenManagerInterface $csrfTokenManager = null, array $defaultThemes = array())
    {
        $engine->addHelpers(array(
            new FormHelper(new FormRenderer(new TemplatingRendererEngine($engine, $defaultThemes), $csrfTokenManager)),
        ));
    }
}
