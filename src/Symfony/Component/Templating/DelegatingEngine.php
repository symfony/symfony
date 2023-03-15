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
    protected $engines = [];

    /**
     * @param EngineInterface[] $engines An array of EngineInterface instances to add
     */
    public function __construct(array $engines = [])
    {
        foreach ($engines as $engine) {
            $this->addEngine($engine);
        }
    }

    public function render(string|TemplateReferenceInterface $name, array $parameters = []): string
    {
        return $this->getEngine($name)->render($name, $parameters);
    }

    /**
     * @return void
     */
    public function stream(string|TemplateReferenceInterface $name, array $parameters = [])
    {
        $engine = $this->getEngine($name);
        if (!$engine instanceof StreamingEngineInterface) {
            throw new \LogicException(sprintf('Template "%s" cannot be streamed as the engine supporting it does not implement StreamingEngineInterface.', $name));
        }

        $engine->stream($name, $parameters);
    }

    public function exists(string|TemplateReferenceInterface $name): bool
    {
        return $this->getEngine($name)->exists($name);
    }

    /**
     * @return void
     */
    public function addEngine(EngineInterface $engine)
    {
        $this->engines[] = $engine;
    }

    public function supports(string|TemplateReferenceInterface $name): bool
    {
        try {
            $this->getEngine($name);
        } catch (\RuntimeException) {
            return false;
        }

        return true;
    }

    /**
     * Get an engine able to render the given template.
     *
     * @throws \RuntimeException if no engine able to work with the template is found
     */
    public function getEngine(string|TemplateReferenceInterface $name): EngineInterface
    {
        foreach ($this->engines as $engine) {
            if ($engine->supports($name)) {
                return $engine;
            }
        }

        throw new \RuntimeException(sprintf('No engine is able to work with the template "%s".', $name));
    }
}
