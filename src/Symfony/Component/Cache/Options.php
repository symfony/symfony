<?php

namespace Symfony\Component\Cache;

use Symfony\Component\Cache\Extension\ExtensionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Options
{
    /**
     * @var OptionsResolverInterface
     */
    private $resolver;

    /**
     * @var array
     */
    private $values = array();

    /**
     * @param ExtensionInterface $extension
     *
     * @param array $values
     */
    public function __construct(ExtensionInterface $extension, array $values)
    {
        $this->resolver = new OptionsResolver();
        $extension->configure($this->resolver);
        $this->merge($values);
    }

    /**
     * @param string $key
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function get($key)
    {
        if (!isset($this->values[$key])) {
            throw new \InvalidArgumentException('Option does not exist.');
        }

        return $this->values[$key];
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return Options
     */
    public function set($key, $value)
    {
        $this->merge(array($key => $value));

        return $this;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->values;
    }

    /**
     * @param array $values
     *
     * @return Options
     */
    public function merge(array $values)
    {
        $this->values = $this->resolve($values);

        return $this;
    }

    /**
     * @param array $values
     *
     * @return array
     */
    public function resolve(array $values)
    {
        foreach ($values as $key => $value) {
            if (!$this->resolver->isKnown($key)) {
                unset($values[$key]);
            }
        }

        return $this->resolver->resolve(array_merge($this->values, $values));
    }
}
