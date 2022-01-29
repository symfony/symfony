<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->set('foo', \stdClass::class)
        ->public()
        ->args([
            new class() implements \Stringable {
                public function __toString(): string
                {
                    return 'foobarccc';
                }
            }
        ]);
};
