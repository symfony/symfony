<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

final class ExpectsIntegerArgumentWrapper
{
    public function __construct(public readonly ExpectsIntegerArgument $b) {}
}
