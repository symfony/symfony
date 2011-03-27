<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer;

interface ThemeRendererInterface
{
    /**
     * Set a renderer variable that is used to render a relevant part of the attached field.
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    function setVar($name, $value);

    /**
     * Set an arbitrary attribute to be rendered with the primary input element of the widget.
     *
     * Examples could include "accesskey" or HTML5 "data-*" attributes.
     *
     * Warning: Do not attempt to overwrite id, name, class, size or maxlength, disabled or requried attributes with this setting.
     * They have their own renderer variables that should be set through {@setVar()}.
     *
     * Important: This is a convenience method, all variables set have to accessible through {@getVar('attr')}
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    function setAttribute($name, $value);
}
