<?php

namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\CommandGenerator\CommandResourceBuilderInterface;
/**
 * Class responsible for building an array of definitions
 * from a given source (e.g. json file) ready to be used
 * for a Custom Command class in order to create custom commands
 * dynamically.
 *
 * @author Alberto Garcia <alberto.garcial@hotmail.com>
 *
 * @api
 */
Class TestCommandResourceBuilder implements CommandResourceBuilderInterface
{

    /**
     * Constructor.
     *
     * @param null $source The source where live our command definitions.
     */
    public function __construct($source = null)
    {
    }

    /**
     * Responsible for parser a given source and turning
     * it into an array usable by a custom comand class.
     *
     * @return array of the definitions
     *
     * @api
     */
    public function buildDefinitions()
    {
        return array(
            'command1' => array(
                'name' => 'name1',
                'description' => 'description',
                'parameters' => array(
                    'param1' => array(
                        'description' => 'description param1',
                    ),
                ),
            ),
            'command2' => array(
                'name' => 'name2',
                'description' => 'description',
                'parameters' => array(
                    'param1' => array(
                        'description' => 'description param1',
                    ),
                ),
            ),
            'command3' => array(
                'name' => 'name3',
                'description' => 'description',
                'parameters' => array(
                    'param1' => array(
                        'description' => 'description param1',
                    ),
                ),
            ),
        );
    }

}
