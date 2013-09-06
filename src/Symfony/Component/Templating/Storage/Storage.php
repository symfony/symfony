<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Storage;

/**
 * Storage is the base class for all storage classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
abstract class Storage
{
    protected $template;

    /**
     * Constructor.
     *
     * @param string $template The template name
     *
     * @api
     */
    public function __construct($template)
    {
        $this->template = $template;
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
     *
     * @api
     */
    abstract public function getContent();
}
