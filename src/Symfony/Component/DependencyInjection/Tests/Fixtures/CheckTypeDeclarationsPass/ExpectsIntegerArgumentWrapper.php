<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\CheckTypeDeclarationsPass;

final readonly class ExpectsIntegerArgumentWrapper
{
    public function __construct(public ExpectsIntegerArgument $b) {}
}
