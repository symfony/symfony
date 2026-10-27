<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" interface is deprecated.', DeprecatedAutoconfiguredInterface::class);

/**
 * A deprecated interface that autoconfiguration applies to.
 */
interface DeprecatedAutoconfiguredInterface
{
}
