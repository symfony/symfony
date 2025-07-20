<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\AsServiceLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[AsServiceLocator('foo_bar')]
final class ServiceLocatorWithAttribute extends ServiceLocator
{
}
