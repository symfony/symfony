<?php

namespace Symfony\Component\DependencyInjection\Configuration;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Configuration\Exception\InvalidTypeException;

class ArrayNode extends BaseNode implements PrototypeNodeInterface
{
    protected $normalizeTransformations;
    protected $children;
    protected $prototype;
    protected $keyAttribute;

    public function __construct($name, NodeInterface $parent = null, array $beforeTransformations = array(), array $afterTransformations = array(), array $normalizeTransformations = array(), $keyAttribute = null)
    {
        parent::__construct($name, $parent, $beforeTransformations, $afterTransformations);

        $this->children = array();
        $this->normalizeTransformations = $normalizeTransformations;
        $this->keyAttribute = $keyAttribute;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setPrototype(PrototypeNodeInterface $node)
    {
        if (count($this->children) > 0) {
            throw new \RuntimeException('An ARRAY node must either have concrete children, or a prototype node.');
        }

        $this->prototype = $node;
    }

    public function addChild(NodeInterface $node)
    {
        $name = $node->getName();
        if (empty($name)) {
            throw new \InvalidArgumentException('Node name cannot be empty.');
        }
        if (isset($this->children[$name])) {
            throw new \InvalidArgumentException(sprintf('The node "%s" already exists.', $name));
        }
        if (null !== $this->prototype) {
            throw new \RuntimeException('An ARRAY node must either have a prototype, or concrete children.');
        }

        $this->children[$name] = $node;
    }

    protected function validateType($value)
    {
        if (!is_array($value)) {
            throw new InvalidTypeException(sprintf(
                'Invalid type for path "%s". Expected array, but got %s',
                $this->getPath(),
                json_encode($value)
            ));
        }
    }

    protected function normalizeValue($value)
    {
        foreach ($this->normalizeTransformations as $transformation) {
            list($singular, $plural) = $transformation;

            if (!isset($value[$singular])) {
                continue;
            }

            $value[$plural] = Extension::normalizeConfig($value, $singular, $plural);
        }

        if (null !== $this->prototype) {
            $normalized = array();
            foreach ($value as $k => $v) {
                if (null !== $this->keyAttribute && is_array($v) && isset($v[$this->keyAttribute])) {
                    $k = $v[$this->keyAttribute];
                }

                $this->prototype->setName($k);
                if (null !== $this->keyAttribute) {
                    $normalized[$k] = $this->prototype->normalize($v);
                } else {
                    $normalized[] = $this->prototype->normalize($v);
                }
            }

            return $normalized;
        }

        $normalized = array();
        foreach ($this->children as $name => $child) {
            if (!array_key_exists($name, $value)) {
                continue;
            }

            $normalized[$name] = $child->normalize($value[$name]);
        }

        return $normalized;
    }
}
