<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Descriptor;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tests\Fixtures\DescriptorApplication1;
use Symfony\Component\Console\Tests\Fixtures\DescriptorApplication2;
use Symfony\Component\Console\Tests\Fixtures\DescriptorCommand1;
use Symfony\Component\Console\Tests\Fixtures\DescriptorCommand2;
use Symfony\Component\Console\Tests\Fixtures\DescriptorCommand5;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ObjectsProvider
{
    public static function getInputArguments()
    {
        return [
            'input_argument_1' => new InputArgument('argument_name', InputArgument::REQUIRED),
            'input_argument_2' => new InputArgument('argument_name', InputArgument::IS_ARRAY, 'argument description'),
            'input_argument_3' => new InputArgument('argument_name', InputArgument::OPTIONAL, 'argument description', 'default_value'),
            'input_argument_4' => new InputArgument('argument_name', InputArgument::REQUIRED, "multiline\nargument description"),
            'input_argument_with_style' => new InputArgument('argument_name', InputArgument::OPTIONAL, 'argument description', '<comment>style</>'),
            'input_argument_with_default_inf_value' => new InputArgument('argument_name', InputArgument::OPTIONAL, 'argument description', \INF),
        ];
    }

    public static function getInputOptions()
    {
        return [
            'input_option_1' => new InputOption('option_name', 'o', InputOption::VALUE_NONE),
            'input_option_2' => new InputOption('option_name', 'o', InputOption::VALUE_OPTIONAL, 'option description', 'default_value'),
            'input_option_3' => new InputOption('option_name', 'o', InputOption::VALUE_REQUIRED, 'option description'),
            'input_option_4' => new InputOption('option_name', 'o', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'option description', []),
            'input_option_5' => new InputOption('option_name', 'o', InputOption::VALUE_REQUIRED, "multiline\noption description"),
            'input_option_6' => new InputOption('option_name', ['o', 'O'], InputOption::VALUE_REQUIRED, 'option with multiple shortcuts'),
            'input_option_deprecated' => new InputOption('option_name', 'o', InputOption::DEPRECATED, 'deprecated option description'),
            'input_option_hidden' => new InputOption('option_name', 'o', InputOption::HIDDEN, 'hidden option description'),
            'input_option_with_style' => new InputOption('option_name', 'o', InputOption::VALUE_REQUIRED, 'option description', '<comment>style</>'),
            'input_option_with_style_array' => new InputOption('option_name', 'o', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'option description', ['<comment>Hello</comment>', '<info>world</info>']),
            'input_option_with_default_inf_value' => new InputOption('option_name', 'o', InputOption::VALUE_OPTIONAL, 'option description', \INF),
        ];
    }

    public static function getInputDefinitions()
    {
        return [
            'input_definition_1' => new InputDefinition(),
            'input_definition_2' => new InputDefinition([new InputArgument('argument_name', InputArgument::REQUIRED)]),
            'input_definition_3' => new InputDefinition([new InputOption('option_name', 'o', InputOption::VALUE_NONE)]),
            'input_definition_4' => new InputDefinition([
                new InputArgument('argument_name', InputArgument::REQUIRED),
                new InputOption('option_name', 'o', InputOption::VALUE_NONE),
            ]),
        ];
    }

    public static function getCommands()
    {
        return [
            'command_1' => new DescriptorCommand1(),
            'command_2' => new DescriptorCommand2(),
            'command_5' => new DescriptorCommand5(),
        ];
    }

    public static function getApplications()
    {
        return [
            'application_1' => new DescriptorApplication1(),
            'application_2' => new DescriptorApplication2(),
        ];
    }
}
