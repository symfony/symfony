<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

/**
 * A console command for retrieving information about container parameters.
 *
 * @author GeLo <geloen.eric@gmail.com>
 */
class ContainerParametersDebugCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('container:parameters:debug')
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A parameter name (foo)'),
            ))
            ->setDescription('Displays current parameters for an application')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command displays all configured parameters:

  <info>php %command.full_name%</info>

To get specific information about a parameter, specify its name:

  <info>php %command.full_name% kernel.root_dir</info>
EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');

        if (null !== $name) {
            $output->write($this->formatValue($this->getContainerBuilder()->getParameter($name)));
        } else {
            $parameters = $this->getContainerBuilder()->getParameterBag()->all();

            // Sort parameters alphabetically
            ksort($parameters);

            $this->outputParameters($output, $parameters);
        }
    }

    protected function outputParameters(OutputInterface $output, $parameters)
    {
        $output->writeln($this->getHelper('formatter')->formatSection('container', 'List of parameters'));

        $terminalDimensions = $this->getApplication()->getTerminalDimensions();
        $maxTerminalWidth = $terminalDimensions[0];
        $maxParameterWidth = 0;
        $maxValueWidth = 0;

        // Determine max parameter & value length
        foreach ($parameters as $parameter => $value) {
            $parameterWidth = strlen($parameter);
            if ($parameterWidth > $maxParameterWidth) {
                $maxParameterWidth = $parameterWidth;
            }

            $valueWith = strlen($this->formatValue($value));
            if ($valueWith > $maxValueWidth) {
                $maxValueWidth = $valueWith;
            }
        }

        $maxValueWidth = min($maxValueWidth, $maxTerminalWidth - $maxParameterWidth - 1);

        $formatTitle = '%-'.($maxParameterWidth + 19).'s %-'.($maxValueWidth + 19).'s';
        $format = '%-'.$maxParameterWidth.'s %-'.$maxValueWidth.'s';

        $output->writeln(sprintf($formatTitle, '<comment>Parameter</comment>', '<comment>Value</comment>'));

        foreach ($parameters as $parameter => $value) {
            $splits = str_split($this->formatValue($value), $maxValueWidth);

            foreach ($splits as $index => $split) {
                if (0 === $index) {
                    $output->writeln(sprintf($format, $parameter, $split));
                } else {
                    $output->writeln(sprintf($format, ' ', $split));
                }
            }
        }
    }

    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @return ContainerBuilder
     */
    protected function getContainerBuilder()
    {
        if (!$this->getApplication()->getKernel()->isDebug()) {
            throw new \LogicException(sprintf('Debug information about the container is only available in debug mode.'));
        }

        if (!is_file($cachedFile = $this->getContainer()->getParameter('debug.container.dump'))) {
            throw new \LogicException(sprintf('Debug information about the container could not be found. Please clear the cache and try again.'));
        }

        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        return $container;
    }

    /**
     * Formats a parameter value.
     *
     * @param mixed $value The paremeter value
     *
     * @return mixed The formatted parameter value.
     */
    protected function formatValue($value)
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        if (null === $value) {
            return 'null';
        }

        if (false === $value) {
            return 'false';
        }

        if (true === $value) {
            return 'true';
        }

        return $value;
    }
}
