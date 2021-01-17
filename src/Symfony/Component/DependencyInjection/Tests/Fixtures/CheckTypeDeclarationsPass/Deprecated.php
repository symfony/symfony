<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

trigger_deprecation('foo/bar', '1.2.3', 'Deprecated class.');

class Deprecated
{
}
