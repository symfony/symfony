<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Annotation;

/**
 * Annotation class for @Route().
 *
 * @Annotation
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class Route
{
    private $path;
    private $name;
    private $requirements;
    private $options;
    private $defaults;
    private $host;
    private $methods;
    private $schemes;

    /**
     * Constructor.
     *
     * @param array $data An array of key/value parameters.
     *
     * @throws \BadMethodCallException
     *
     * @since v2.0.0
     */
    public function __construct(array $data)
    {
        $this->requirements = array();
        $this->options = array();
        $this->defaults = array();
        $this->methods = array();
        $this->schemes = array();

        if (isset($data['value'])) {
            $data['path'] = $data['value'];
            unset($data['value']);
        }

        foreach ($data as $key => $value) {
            $method = 'set'.str_replace('_', '', $key);
            if (!method_exists($this, $method)) {
                throw new \BadMethodCallException(sprintf("Unknown property '%s' on annotation '%s'.", $key, get_class($this)));
            }
            $this->$method($value);
        }
    }

    /**
     * @deprecated Deprecated in 2.2, to be removed in 3.0. Use setPath instead.
     *
     * @since v2.0.0
     */
    public function setPattern($pattern)
    {
        $this->path = $pattern;
    }

    /**
     * @deprecated Deprecated in 2.2, to be removed in 3.0. Use getPath instead.
     *
     * @since v2.0.0
     */
    public function getPattern()
    {
        return $this->path;
    }

    /**
     * @since v2.2.0
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @since v2.2.0
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @since v2.2.0
     */
    public function setHost($pattern)
    {
        $this->host = $pattern;
    }

    /**
     * @since v2.2.0
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @since v2.0.0
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @since v2.0.0
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @since v2.0.0
     */
    public function setRequirements($requirements)
    {
        $this->requirements = $requirements;
    }

    /**
     * @since v2.0.0
     */
    public function getRequirements()
    {
        return $this->requirements;
    }

    /**
     * @since v2.0.0
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * @since v2.0.0
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @since v2.0.0
     */
    public function setDefaults($defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * @since v2.0.0
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     * @since v2.2.0
     */
    public function setSchemes($schemes)
    {
        $this->schemes = is_array($schemes) ? $schemes : array($schemes);
    }

    /**
     * @since v2.2.0
     */
    public function getSchemes()
    {
        return $this->schemes;
    }

    /**
     * @since v2.2.0
     */
    public function setMethods($methods)
    {
        $this->methods = is_array($methods) ? $methods : array($methods);
    }

    /**
     * @since v2.2.0
     */
    public function getMethods()
    {
        return $this->methods;
    }
}
