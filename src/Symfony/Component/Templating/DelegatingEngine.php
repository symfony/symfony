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
 * DelegatingEngine selects an engine for a given template.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DelegatingEngine implements EngineInterface, StreamingEngineInterface
{
    /**
     * @var EngineInterface[]
     */
    protected $engines = array();

    /**
     * @param EngineInterface[] $engines An array of EngineInterface instances to add
     */
    public function __construct(array $engines = array())
    {
        foreach ($engines as $engine) {
            $this->addEngine($engine);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function render($name, array $parameters = array())
    {
        return $this->getEngine($name)->render($name, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function stream($name, array $parameters = array())
    {
        $engine = $this->getEngine($name);
        if (!$engine instanceof StreamingEngineInterface) {
            throw new \LogicException(sprintf('Template "%s" cannot be streamed as the engine supporting it does not implement StreamingEngineInterface.', $name));
        }

        $engine->stream($name, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        return $this->getEngine($name)->exists($name);
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
     * {@inheritdoc}
     */
    public function supports($name)
    {
        try {
            $this->getEngine($name);
        } catch (\RuntimeException $e) {
            return false;
        }

        return true;
    }

    /**
     * Get an engine able to render the given template.
     *
     * @param string|TemplateReferenceInterface $name A template name or a TemplateReferenceInterface instance
     *
     * @return EngineInterface The engine
     *
     * @throws \RuntimeException if no engine able to work with the template is found
     */
    public function getEngine($name)
    {
        foreach ($this->engines as $engine) {
            if ($engine->supports($name)) {
                return $engine;
            }
        }

        throw new \RuntimeException(sprintf('No engine is able to work with the template "%s".', $name));
    }
}
