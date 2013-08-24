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
 *
 * @since v2.0.0
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
     *
     * @since v2.0.0
     */
    public function __construct($template)
    {
        $this->template = $template;
    }

    /**
     * Returns the object string representation.
     *
     * @return string The template name
     *
     * @since v2.0.0
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
     *
     * @since v2.0.0
     */
    abstract public function getContent();
}
