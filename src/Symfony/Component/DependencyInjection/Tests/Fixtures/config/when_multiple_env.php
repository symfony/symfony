<?php

use Symfony\Component\DependencyInjection\Attribute\When;

return #[When(env: ['test', 'prod'])] function () {
    throw new RuntimeException('This code should not be run.');
};
