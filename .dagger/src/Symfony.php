<?php

declare(strict_types=1);

namespace DaggerModule;

use Dagger\Attribute\DaggerFunction;
use Dagger\Attribute\DaggerObject;
use Dagger\Attribute\Doc;
use Dagger\Container;
use Dagger\Directory;

use function Dagger\dag;

#[DaggerObject]
#[Doc('A generated module for Symfony functions')]
class Symfony
{
    #[DaggerFunction]
    #[Doc('Run phpstan across the entire symfony codebase')]
    // dagger call phpstan --source=.
    public function phpstan(Directory $source): Container
    {
        return dag()
            ->phpstan()
            ->analyze('8.2', $source, 'src');
    }

    #[DaggerFunction]
    #[Doc('Run phpstan across all components in Symfony')]
    // dagger call phpstan-components --source=.
    public function phpstanComponents(Directory $source): Container
    {
        return dag()
            ->phpstan()
            ->analyze('8.2', $source, 'src/Symfony/Component');
    }

    #[DaggerFunction]
    #[Doc('Run phpstan across on a specific Symfony component')]
    // dagger call phpstan-component --source=. --component=Asset
    public function phpstanComponent(Directory $source, string $component): Container
    {
        return dag()
            ->phpstan()
            ->analyze('8.2', $source, "src/Symfony/Component/$component");
    }


    #[DaggerFunction]
    #[Doc('Run psalm across the entire symfony codebase')]
    // dagger call psalm --source=.
    public function psalm(Directory $source): Container
    {
        return dag()
            ->psalm()
            ->run('8.2', $source, 'src');
    }


    #[DaggerFunction]
    #[Doc('Run phpstan across all components in Symfony')]
    // dagger call psalm-components --source=.
    public function psalmComponents(Directory $source): Container
    {
        return dag()
            ->psalm()
            ->run('8.2', $source, 'src/Symfony/Component');
    }

    #[DaggerFunction]
    #[Doc('Run psalm across on a specific Symfony component')]
    // dagger call psalm-component --source=. --component=Asset
    public function psalmComponent(Directory $source, string $component): Container
    {
        return dag()
            ->psalm()
            ->run('8.2', $source, "src/Symfony/Component/$component");
    }
}
