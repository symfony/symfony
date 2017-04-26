<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Console\CommandGenerator;

/**
 * Class responsible for building an array of definitions
 * from a given source (e.g. json file) ready to be used
 * for a Custom Command class in order to create custom commands
 * dynamically.
 *
 * @author Alberto Garcia Lamela <alberto.garcial@hotmail.com>
 *
 * @api
 */
Interface CommandResourceBuilderInterface
{

    /**
     * Constructor.
     *
     * @param null $source The source where live our command definitions.
     */
    public function __construct($source = null);

    /**
     * Responsible for parser a given source and turning
     * it into an array usable by a custom comand class.
     *
     * @return array of the definitions
     *
     * @api
     */
    public function buildDefinitions();

}
