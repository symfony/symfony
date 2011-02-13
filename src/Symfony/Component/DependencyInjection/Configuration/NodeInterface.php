<?php

namespace Symfony\Component\DependencyInjection\Configuration;

/**
 * Common Interface among all nodes.
 *
 * In most cases, it is better to inherit from BaseNode instead of implementing
 * this interface yourself.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface NodeInterface
{
    /**
     * Returns the name of the node.
     *
     * @return string The name of the node
     */
    function getName();

    /**
     * Returns the path of the node.
     *
     * @return string The node path
     */
    function getPath();
    
    /**
     * Returns true when the node is required.
     *
     * @return boolean If the node is required
     */
    function isRequired();
    
    /**
     * Returns true when the node has a default value.
     *
     * @return boolean If the node has a default value
     */
    function hasDefaultValue();
    
    /**
     * Returns the default value of the node.
     *
     * @return mixed The default value
     * @throws \RuntimeException if the node has no default value
     */
    function getDefaultValue();
    
    /**
     * Normalizes the supplied value.
     *
     * @param mixed $value The value to normalize
     * @return mixed The normalized value
     */
    function normalize($value);
    
    /**
     * Merges two values together.
     *
     * @param mixed $leftSide 
     * @param mixed $rightSide 
     * @return mixed The merged values
     */
    function merge($leftSide, $rightSide);
    
    /**
     * Finalizes a value.
     *
     * @param mixed $value The value to finalize
     * @return mixed The finalized value
     */
    function finalize($value);
}