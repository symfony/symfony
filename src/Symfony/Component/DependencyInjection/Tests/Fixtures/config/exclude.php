<?php

use Symfony\Component\DependencyInjection\Attribute\Exclude;

return #[Exclude] function () {
    throw new RuntimeException('This code should not be run.');
};
