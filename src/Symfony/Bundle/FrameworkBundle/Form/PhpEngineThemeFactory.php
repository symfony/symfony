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

use Symfony\Component\Form\Renderer\Theme\ThemeFactoryInterface;
use Symfony\Component\Templating\PhpEngine;

/**
 * Constructs PhpEngineTheme instances
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class PhpEngineThemeFactory implements ThemeFactoryInterface
{
    /**
     * @var PhpEngine
     */
    private $engine;

    /**
     * @var string
     */
    private $defaultTemplateDir;

    public function __construct(PhpEngine $engine, $defaultTemplateDir = null)
    {
        $this->engine = $engine;
        $this->defaultTemplateDir = $defaultTemplateDir;
    }

    /**
     * @see Symfony\Component\Form\Renderer\Theme\ThemeFactoryInterface::create()
     */
    public function create($templateDir = null)
    {
        return new PhpEngineTheme($this->engine, $templateDir ?: $this->defaultTemplateDir);
    }
}