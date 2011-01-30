<?php

namespace Symfony\Component\DependencyInjection\Configuration;

abstract class BaseNode implements NodeInterface
{
    protected $name;
    protected $parent;
    protected $beforeTransformations;
    protected $afterTransformations;
    protected $nodeFactory;

    public function __construct($name, NodeInterface $parent = null, $beforeTransformations = array(), $afterTransformations = array())
    {
        if (false !== strpos($name, '.')) {
            throw new \InvalidArgumentException('The name must not contain ".".');
        }

        $this->name = $name;
        $this->parent = $parent;
        $this->beforeTransformations = $beforeTransformations;
        $this->afterTransformations = $afterTransformations;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPath()
    {
        $path = $this->name;

        if (null !== $this->parent) {
            $path = $this->parent->getPath().'.'.$path;
        }

        return $path;
    }

    public final function normalize($value)
    {
        // run before transformations
        foreach ($this->beforeTransformations as $transformation) {
            $value = $transformation($value);
        }

        // validate type
        $this->validateType($value);

        // normalize value
        $value = $this->normalizeValue($value);

        // run after transformations
        foreach ($this->afterTransformations as $transformation) {
            $value = $transformation($value);
        }

        return $value;
    }

    abstract protected function validateType($value);
    abstract protected function normalizeValue($value);
}