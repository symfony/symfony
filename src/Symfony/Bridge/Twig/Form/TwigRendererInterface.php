<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Form;

use Symfony\Component\Form\FormRendererInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated in 2.8, to be removed in 3.0.
 */
interface TwigRendererInterface extends FormRendererInterface
{
    /**
     * Sets Twig's environment.
     *
     * @param \Twig_Environment $environment
     */
    public function setEnvironment(\Twig_Environment $environment);
}
