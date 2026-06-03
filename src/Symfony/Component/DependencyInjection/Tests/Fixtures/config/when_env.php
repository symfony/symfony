<?php

use Symfony\Component\DependencyInjection\Attribute\When;

return #[When(env: 'prod')] function () {
    throw new RuntimeException('This code should not be run.');
};
