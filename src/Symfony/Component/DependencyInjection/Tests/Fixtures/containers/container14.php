<?php

namespace Container14;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/*
 * This file is included in Tests\Dumper\GraphvizDumperTest::testDumpWithFrozenCustomClassContainer
 * and Tests\Dumper\XmlDumperTest::testCompiledContainerCanBeDumped.
 */
if (!class_exists('Container14\ProjectServiceContainer')) {
    class ProjectServiceContainer extends ContainerBuilder
    {
    }
}

return new ProjectServiceContainer();
