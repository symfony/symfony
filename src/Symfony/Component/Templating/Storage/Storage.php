<?php

namespace Symfony\Component\Templating\Storage;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Storage is the base class for all storage classes.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Storage
{
    protected $renderer;
    protected $template;

    /**
     * Constructor.
     *
     * @param string $template The template name
     * @param string $renderer The renderer name
     */
    public function __construct($template, $renderer = null)
    {
        $this->template = $template;
        $this->renderer = $renderer;
    }

    /**
     * Returns the object string representation.
     *
     * @return string The template name
     */
    public function __toString()
    {
        return (string) $this->template;
    }

    /**
     * Returns the content of the template.
     *
     * @return string The template content
     */
    abstract public function getContent();

    /**
     * Gets the renderer.
     *
     * @return string|null The renderer name or null if no renderer is stored for this template
     */
    public function getRenderer()
    {
        return $this->renderer;
    }
}
