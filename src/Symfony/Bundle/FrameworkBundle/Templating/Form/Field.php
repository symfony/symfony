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
 * Field wraps a Form\FieldInterface instance.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Field extends BaseField
{
    static protected $cache = array();

    public function render($template = null)
    {
        if ($this->field instanceof Form || get_class($this->field) === 'Symfony\Component\Form\FieldGroup') {
            throw new \LogicException(sprintf('Cannot render a group field as a row (%s)', $this->field->getKey()));
        }

        if (null === $template) {
            $template = sprintf('FrameworkBundle:Form:group/%s/row.php', $this->theme);
        }

        return $this->engine->render($template, array('field' => $this));
    }

    public function data()
    {
        return $this->field->getData();
    }

    public function widget(array $attributes = array(), $template = null)
    {
        if ($this->field instanceof Form || get_class($this->field) === 'Symfony\Component\Form\FieldGroup') {
            throw new \LogicException(sprintf('Cannot render a group field (%s)', $this->field->getKey()));
        }

        if (null === $template) {
            $template = $this->getTemplate();
        }

        return $this->engine->render($template, array(
            'field'      => $this,
            'origin'     => $this->field,
            'attributes' => array_merge($this->field->getAttributes(), $attributes),
            'generator'  => $this->generator,
        ));
    }

    public function label($label = false, $template = null)
    {
        if (null === $template) {
            $template = 'FrameworkBundle:Form:label.php';
        }

        return $this->engine->render($template, array(
            'field' => $this,
            'id'    => $this->field->getId(),
            'key'   => $this->field->getKey(),
            'label' => $label ? $label : ucfirst(strtolower(str_replace('_', ' ', $this->field->getKey())))
        ));
    }

    public function errors($template = null)
    {
        if (null === $template) {
            $template = 'FrameworkBundle:Form:errors.php';
        }

        return $this->engine->render($template, array('field' => $this, 'errors' => $this->field->getErrors()));
    }

    protected function getTemplate()
    {
        $class = get_class($this->field);

        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        // find a template for the given class or one of its parents
        do {
            $parts = explode('\\', $class);
            $c = array_pop($parts);

            $underscore = strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($c, '_', '.')));

            if ($this->engine->exists($template = 'FrameworkBundle:Form:widget/'.$underscore.'.php')) {
                return self::$cache[$class] = $template;
            }
        } while (false !== $class = get_parent_class($class));

        throw new \RuntimeException(sprintf('Unable to find a template to render the "%s" widget.', $this->field->getKey()));
    }
}
