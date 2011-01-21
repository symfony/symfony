<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating;

/**
 * DelegatingEngine selects an engine for a given template.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DelegatingEngine implements EngineInterface
{
    protected $engines;

    /**
     * Constructor.
     *
     * @param array $engines An array of EngineInterface instances to add
     */
    public function __construct(array $engines = array())
    {
        $this->engines = array();
        foreach ($engines as $engine) {
            $this->addEngine($engine);
        }
    }

    /**
     * Renders a template.
     *
     * @param string $name       A template name
     * @param array  $parameters An array of parameters to pass to the template
     *
     * @return string The evaluated template as a string
     *
     * @throws \InvalidArgumentException if the template does not exist
     * @throws \RuntimeException         if the template cannot be rendered
     */
    public function render($name, array $parameters = array())
    {
        return $this->getEngine($name)->render($name, $parameters);
    }

    /**
     * Returns true if the template exists.
     *
     * @param string $name A template name
     *
     * @return Boolean true if the template exists, false otherwise
     */
    public function exists($name)
    {
        return $this->getEngine($name)->exists($name);
    }

    /**
     * Loads the given template.
     *
     * @param string $name A template name
     *
     * @return \Twig_TemplateInterface A \Twig_TemplateInterface instance
     *
     * @throws \Twig_Error_Loader if the template cannot be found
     */
    public function load($name)
    {
        return $this->getEngine($name)->load($name);
    }

    /**
     * Adds an engine.
     *
     * @param EngineInterface $engine An EngineInterface instance
     */
    public function addEngine(EngineInterface $engine)
    {
        $this->engines[] = $engine;
    }

    /**
     * Returns true if this class is able to render the given template.
     *
     * @param string $name A template name
     *
     * @return Boolean True if this class supports the given template, false otherwise
     */
    public function supports($name)
    {
        foreach ($this->engines as $engine) {
            if ($engine->supports($name)) {
                return true;
            }
        }

        return false;
    }

    protected function getEngine($name)
    {
        foreach ($this->engines as $engine) {
            if ($engine->supports($name)) {
                return $engine;
            }
        }

        throw new \RuntimeException(sprintf('No engine is able to work with the "%s" template.', $name));
    }
}
