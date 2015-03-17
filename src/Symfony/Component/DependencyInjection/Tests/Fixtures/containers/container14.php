<?php

namespace Container14;

use Symfony\Component\DependencyInjection\ContainerBuilder;

if (!class_exists('Container14\ProjectServiceContainer')) {
    class ProjectServiceContainer extends ContainerBuilder
    {
    }
}

return new ProjectServiceContainer();
