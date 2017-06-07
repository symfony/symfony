<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating;

/**
 * Internal representation of a template.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
class TemplateReference implements TemplateReferenceInterface
{
    protected $parameters;

    /**
     * Implements var_export support
     *
     * @param array $data
     *
     * @return static
     */
    public static function __set_state(array $data)
    {
        $template = new static();

        foreach ($data['parameters'] as $parameter => $value) {
            $template->set($parameter, $value);
        }

        return $template;
    }

    public function __construct($name = null, $engine = null)
    {
        $this->parameters = array(
            'name' => $name,
            'engine' => $engine,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->getLogicalName();
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value)
    {
        if (array_key_exists($name, $this->parameters)) {
            $this->parameters[$name] = $value;
        } else {
            throw new \InvalidArgumentException(sprintf('The template does not support the "%s" parameter.', $name));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (array_key_exists($name, $this->parameters)) {
            return $this->parameters[$name];
        }

        throw new \InvalidArgumentException(sprintf('The template does not support the "%s" parameter.', $name));
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return $this->parameters['name'];
    }

    /**
     * {@inheritdoc}
     */
    public function getLogicalName()
    {
        return $this->parameters['name'];
    }
}
