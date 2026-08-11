<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Conditional;

use Symfony\Component\DependencyInjection\Attribute\WhenClassExists;
use Symfony\Component\DependencyInjection\Attribute\WhenMissingService;
use Symfony\Component\DependencyInjection\Attribute\WhenParameter;

#[WhenClassExists(\stdClass::class)]
#[WhenMissingService('app.manager')]
#[WhenParameter('app.enabled')]
class ConditionalService
{
}
