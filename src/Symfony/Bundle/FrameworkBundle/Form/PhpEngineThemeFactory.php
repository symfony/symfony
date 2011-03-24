<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Form;

use Symfony\Component\Form\Renderer\Theme\FormThemeFactoryInterface;
use Symfony\Component\Templating\PhpEngine;

/**
 * Constructs PhpEngineTheme instances
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class PhpEngineThemeFactory implements FormThemeFactoryInterface
{
    /**
     * @var PhpEngine
     */
    private $engine;

    public function __construct(PhpEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * @see Symfony\Component\Form\Renderer\Theme\FormThemeFactoryInterface::create()
     */
    public function create($template = null)
    {
        return new PhpEngineTheme($this->engine, $template);
    }
}