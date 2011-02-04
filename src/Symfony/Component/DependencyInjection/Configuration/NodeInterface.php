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
    function getName();
    function getPath();
    function isRequired();
    function hasDefaultValue();
    function getDefaultValue();
    function normalize($value);
    function merge($leftSide, $rightSide);
}