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

interface RendererInterface
{
    function setParameter($name, $value);

    function getWidget(array $attributes = array(), array $parameters = array());

    function getErrors(array $attributes = array(), array $parameters = array());

    function getRow(array $attributes = array(), array $parameters = array());

    function getHidden(array $attributes = array(), array $parameters = array());

    /**
     * Renders the label of the given field
     *
     * @param FieldInterface $field  The field to render the label for
     * @param array $params          Additional variables passed to the template
     */
    function getLabel($label = null, array $attributes = array(), array $parameters = array());
}