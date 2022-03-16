<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\AcmeConfig;

class NestedConfig
{
}

class_alias(NestedConfig::class, '\\Symfony\\Config\\AcmeConfig\\NestedConfig');

