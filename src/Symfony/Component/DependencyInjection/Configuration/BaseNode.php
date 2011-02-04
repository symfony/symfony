<?php

namespace Symfony\Component\DependencyInjection\Configuration;

use Symfony\Component\DependencyInjection\Configuration\Exception\Exception;
use Symfony\Component\DependencyInjection\Configuration\Exception\ForbiddenOverwriteException;

/**
 * The base node class
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class BaseNode implements NodeInterface
{
    protected $name;
    protected $parent;
    protected $normalizationClosures;
    protected $finalValidationClosures;
    protected $allowOverwrite;
    protected $required;
    protected $equivalentValues;

    public function __construct($name, NodeInterface $parent = null)
    {
        if (false !== strpos($name, '.')) {
            throw new \InvalidArgumentException('The name must not contain ".".');
        }

        $this->name = $name;
        $this->parent = $parent;
        $this->normalizationClosures = array();
        $this->finalValidationClosures = array();
        $this->allowOverwrite = true;
        $this->required = false;
        $this->equivalentValues = array();
    }

    public function addEquivalentValue($originalValue, $equivalentValue)
    {
        $this->equivalentValues[] = array($originalValue, $equivalentValue);
    }

    public function setRequired($boolean)
    {
        $this->required = (Boolean) $boolean;
    }

    public function setAllowOverwrite($allow)
    {
        $this->allowOverwrite = (Boolean) $allow;
    }

    public function setNormalizationClosures(array $closures)
    {
        $this->normalizationClosures = $closures;
    }

    public function setFinalValidationClosures(array $closures)
    {
        $this->finalValidationClosures = $closures;
    }

    public function isRequired()
    {
        return $this->required;
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

    public final function merge($leftSide, $rightSide)
    {
        if (!$this->allowOverwrite) {
            throw new ForbiddenOverwriteException(sprintf(
                'Configuration path "%s" cannot be overwritten. You have to '
               .'define all options for this path, and any of its sub-paths in '
               .'one configuration section.',
                $this->getPath()
            ));
        }

        $this->validateType($leftSide);
        $this->validateType($rightSide);

        return $this->mergeValues($leftSide, $rightSide);
    }

    public final function normalize($value)
    {
        // run custom normalization closures
        foreach ($this->normalizationClosures as $closure) {
            $value = $closure($value);
        }

        // replace value with their equivalent
        foreach ($this->equivalentValues as $data) {
            if ($data[0] === $value) {
                $value = $data[1];
            }
        }

        // validate type
        $this->validateType($value);

        // normalize value
        return $this->normalizeValue($value);
    }

    public final function finalize($value)
    {
        $this->validateType($value);

        $value = $this->finalizeValue($value);

        // Perform validation on the final value if a closure has been set.
        // The closure is also allowed to return another value.
        foreach ($this->finalValidationClosures as $closure) {
            try {
                $value = $closure($value);
            } catch (Exception $correctEx) {
                throw $correctEx;
            } catch (\Exception $invalid) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid configuration for path "%s": %s',
                    $this->getPath(),
                    $invalid->getMessage()
                ), $invalid->getCode(), $invalid);
            }
        }

        return $value;
    }

    abstract protected function validateType($value);
    abstract protected function normalizeValue($value);
    abstract protected function mergeValues($leftSide, $rightSide);
    abstract protected function finalizeValue($value);
}