<?php

namespace Symfony\Component\DependencyInjection;

/**
 * Scope Interface.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface ScopeInterface
{
    function getName();
    function getParentName();
}