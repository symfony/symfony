<?php

namespace Symfony\Component\HttpKernel\ControllerConfiguration;

interface ConfigurationInterface
{
    const TARGET_CLASS = 'class';
    const TARGET_ARGUMENTS = 'arguments';
    const TARGET_RESPONSE = 'response';

    public function getTarget(): array;
}
