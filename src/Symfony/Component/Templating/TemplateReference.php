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
 *
 * @api
 */
class TemplateReference implements TemplateReferenceInterface
{
    protected $parameters;

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
     *
     * @api
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
     *
     * @api
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
     *
     * @api
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function getPath()
    {
        return $this->parameters['name'];
    }

    /**
     * {@inheritdoc}
     *
     * @api
     */
    public function getLogicalName()
    {
        return $this->parameters['name'];
    }
}
