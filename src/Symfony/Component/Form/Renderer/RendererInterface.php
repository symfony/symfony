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
    function setVar($name, $value);

    function getVar($name);

    function getWidget(array $vars = array());

    function getErrors(array $vars = array());

    function getRow(array $vars = array());

    function getRest(array $vars = array());

    /**
     * Renders the label of the given field
     *
     * @param FieldInterface $field  The field to render the label for
     * @param array $params          Additional variables passed to the template
     */
    function getLabel($label = null, array $vars = array());
}