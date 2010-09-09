<?php

namespace Symfony\Bundle\FrameworkBundle\Templating\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * FieldGroup wraps a Form\FieldGroupInterface instance.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FieldGroup extends BaseField
{
    /**
     * Renders the form tag.
     *
     * This method only renders the opening form tag.
     * You need to close it after the form rendering.
     *
     * This method takes into account the multipart widgets.
     *
     * @param  string $url         The URL for the action
     * @param  array  $attributes  An array of HTML attributes
     *
     * @return string An HTML representation of the opening form tag
     */
    public function form($url, array $attributes = array())
    {
        return sprintf('<form%s>', $this->generator->attributes(array_merge(array(
            'action' => $url,
            'method' => isset($attributes['method']) ? strtolower($attributes['method']) : 'post',
            'enctype' => $this->field->isMultipart() ? 'multipart/form-data' : null,
        ), $attributes)));
    }

    public function render($template = null)
    {
        if (null === $template) {
            $template = sprintf('FrameworkBundle:Form:group/%s/field_group.php', $this->theme);
        }

        return $this->engine->render($template, array('group' => $this));
    }

    public function hidden($template = null)
    {
        if (null === $template) {
            $template = 'FrameworkBundle:Form:hidden.php';
        }

        return $this->engine->render($template, array(
            'group'  => $this,
            'hidden' => $this->wrapFields($this->field->getHiddenFields(true))
        ));
    }

    public function errors($template = null)
    {
        if (null === $template) {
            $template = 'FrameworkBundle:Form:errors.php';
        }

        return $this->engine->render($template, array(
            'group'  => $this,
            'errors' => $this->field->getErrors()
        ));
    }
}
