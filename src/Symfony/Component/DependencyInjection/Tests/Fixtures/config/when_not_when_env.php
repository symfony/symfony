<?php

use Symfony\Component\DependencyInjection\Attribute\WhenNot;
use Symfony\Component\DependencyInjection\Attribute\When;

return #[When(env: 'dev')] #[WhenNot(env: 'prod')] function () {
    throw new RuntimeException('This code should not be run.');
};
