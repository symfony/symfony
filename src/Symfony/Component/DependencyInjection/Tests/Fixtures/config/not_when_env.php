<?php

use Symfony\Component\DependencyInjection\Attribute\WhenNot;

return #[WhenNot(env: 'prod')] function () {
    throw new RuntimeException('This code should not be run.');
};
