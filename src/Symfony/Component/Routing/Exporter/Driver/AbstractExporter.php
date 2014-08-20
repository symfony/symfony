<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Exporter\Driver;
use Symfony\Component\Routing\RouteCollection;

/**
 * Base abstract class to convert route definitions.
 *
 * @author David Tengeri <dtengeri@gmail.com>
 */
abstract class AbstractExporter
{

    protected $outputDir;

    /**
     * Creates a new exporter and sets its output directory to $destination.
     *
     * @param string $destination The path to the output directory.
     */
    public function __construct($destination)
    {
        $this->outputDir = $destination;
    }

    /**
     * Saves the defined routes into a routing file in the desired format.
     *
     * @param RouteCollection $routes
     */
    public abstract function exportRoutes(RouteCollection $routes);

    /**
     * Saves the given routes in a routing file.
     * Creates the output directory if it is not exists.
     *
     * @param RouteCollection $routes The routes to save.
     *
     * @throws \InvalidArgumentException If the output directory is not writable.
     */
    public function export(RouteCollection $routes)
    {
        // Create the output directory.
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }

        // Check if it is writable.
        if (!is_writable($this->outputDir)) {
            throw new \InvalidArgumentException(sprintf('The output directory "%s" is not writable.', $this->outputDir));
        }

        $this->exportRoutes($routes);
    }
} 