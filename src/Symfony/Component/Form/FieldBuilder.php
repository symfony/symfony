<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Validator\FieldValidatorInterface;
use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\Form\Renderer\Plugin\RendererPluginInterface;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FieldBuilder
{
    private $name;

    private $data;

    private $dispatcher;

    private $factory;

    private $disabled;

    private $required;

    private $renderer;

    private $rendererVars = array();

    private $clientTransformer;

    private $normalizationTransformer;

    private $theme;

    private $validator;

    private $attributes = array();

    private $parent;

    public function __construct(ThemeInterface $theme,
            EventDispatcherInterface $dispatcher)
    {
        $this->theme = $theme;
        $this->dispatcher = $dispatcher;
    }

    public function setFormFactory(FormFactoryInterface $factory)
    {
        $this->factory = $factory;

        return $this;
    }

    public function getFormFactory()
    {
        return $this->factory;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setParent(FieldBuilder $builder)
    {
        $this->parent = $builder;

        return $this;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function end()
    {
        return $this->parent;
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setDisabled($disabled)
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function getDisabled()
    {
        return $this->disabled;
    }

    /**
     * Sets whether this field is required to be filled out when bound.
     *
     * @param Boolean $required
     */
    public function setRequired($required)
    {
        $this->required = $required;

        return $this;
    }

    public function getRequired()
    {
        return $this->required;
    }

    public function setValidator(FieldValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * Adds an event listener for events on this field
     *
     * @see Symfony\Component\EventDispatcher\EventDispatcherInterface::addEventListener
     */
    public function addEventListener($eventNames, $listener, $priority = 0)
    {
        $this->dispatcher->addListener($eventNames, $listener, $priority);

        return $this;
    }

    /**
     * Adds an event subscriber for events on this field
     *
     * @see Symfony\Component\EventDispatcher\EventDispatcherInterface::addEventSubscriber
     */
    public function addEventSubscriber(EventSubscriberInterface $subscriber, $priority = 0)
    {
        $this->dispatcher->addSubscriber($subscriber, $priority);

        return $this;
    }

    protected function buildDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Sets the DataTransformer.
     *
     * @param DataTransformerInterface $clientTransformer
     */
    public function setNormTransformer(DataTransformerInterface $normalizationTransformer = null)
    {
        $this->normalizationTransformer = $normalizationTransformer;

        return $this;
    }

    public function getNormTransformer()
    {
        return $this->normalizationTransformer;
    }

    /**
     * Sets the DataTransformer.
     *
     * @param DataTransformerInterface $clientTransformer
     */
    public function setClientTransformer(DataTransformerInterface $clientTransformer = null)
    {
        $this->clientTransformer = $clientTransformer;

        return $this;
    }

    public function getClientTransformer()
    {
        return $this->clientTransformer;
    }

    /**
     * Sets the renderer
     *
     * @param RendererInterface $renderer
     */
    public function setRenderer(RendererInterface $renderer)
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function addRendererPlugin(RendererPluginInterface $plugin)
    {
        $this->rendererVars[] = $plugin;

        return $this;
    }

    public function setRendererVar($name, $value)
    {
        $this->rendererVars[$name] = $value;

        return $this;
    }

    protected function buildRenderer()
    {
        if (!$this->renderer) {
            $this->renderer = new DefaultRenderer($this->theme, 'text');
        }

        foreach ($this->rendererVars as $name => $value) {
            if ($value instanceof RendererPluginInterface) {
                $this->renderer->addPlugin($value);
                continue;
            }

            $this->renderer->setVar($name, $value);
        }

        return $this->renderer;
    }

    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    public function getAttribute($name)
    {
        return $this->attributes[$name];
    }

    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getInstance()
    {
        $instance = new Field(
            $this->getName(),
            $this->buildDispatcher(),
            $this->buildRenderer(),
            $this->getClientTransformer(),
            $this->getNormTransformer(),
            $this->getValidator(),
            $this->getRequired(),
            $this->getDisabled(),
            $this->getAttributes()
        );

        if ($this->getData()) {
            $instance->setData($this->getData());
        }

        return $instance;
    }
}