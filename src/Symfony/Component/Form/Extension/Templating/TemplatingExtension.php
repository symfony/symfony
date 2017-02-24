<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Templating;

use Symfony\Component\Form\AbstractExtension;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Templating\PhpEngine;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\FormHelper;

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
