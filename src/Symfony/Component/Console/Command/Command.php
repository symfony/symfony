<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Descriptor\XmlDescriptor;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\HelperSet;

/**
 * Base class for all commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Command extends CommandConfiguration
{
    /**
     * @var Application|null
     */
    private $application;
    private $ignoreValidationErrors = false;
    private $applicationDefinitionMerged = false;
    private $applicationDefinitionMergedWithArgs = false;
    private $code;
    private $helperSet;

    /**
     * Constructor.
     *
     * @param string|null               $name          The name of the command; passing null means it must be set in configure()
     * @param CommandConfiguration|null $configuration Pass the command configuration if it was defined separately from the command
     *
     * @throws \LogicException When the command name is empty
     *
     * @api
     */
    public function __construct($name = null, CommandConfiguration $configuration = null)
    {
        parent::__construct($this);

        if (null !== $name) {
            $this->setName($name);
        }
        if (null !== $configuration) {
            $this->setConfiguration($configuration);
        }

        $this->configure();

        if (!$this->getName()) {
            throw new \LogicException(sprintf('The command defined in "%s" cannot have an empty name.', get_class($this)));
        }
    }

    /**
     * Ignores validation errors.
     *
     * This is mainly useful for the help command.
     */
    public function ignoreValidationErrors()
    {
        $this->ignoreValidationErrors = true;
    }

    /**
     * Sets the application instance for this command.
     *
     * @param Application $application An Application instance
     *
     * @api
     */
    public function setApplication(Application $application = null)
    {
        $this->application = $application;
        if ($application) {
            $this->setHelperSet($application->getHelperSet());
        } else {
            $this->helperSet = null;
        }
    }

    /**
     * Sets the helper set.
     *
     * @param HelperSet $helperSet A HelperSet instance
     */
    public function setHelperSet(HelperSet $helperSet)
    {
        $this->helperSet = $helperSet;
    }

    /**
     * Gets the helper set.
     *
     * @return HelperSet A HelperSet instance
     */
    public function getHelperSet()
    {
        return $this->helperSet;
    }

    /**
     * Gets the application instance for this command.
     *
     * @return Application An Application instance
     *
     * @api
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * Checks whether the command is enabled or not in the current environment.
     *
     * Override this to check for x or y and return false if the command can not
     * run properly under the current conditions.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|int null or 0 if everything went fine, or an error code
     *
     * @throws \LogicException When this abstract method is not implemented
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        throw new \LogicException('You must override the execute() method in the concrete command class.');
    }

    /**
     * Interacts with the user.
     *
     * This method is executed before the InputDefinition is validated.
     * This means that this is the only place where the command can
     * interactively ask for values of missing required arguments.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * Initializes the command just after the input has been validated.
     *
     * This is mainly useful when a lot of commands extends one main command
     * where some things need to be initialized based on the input arguments and options.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
    }

    /**
     * Runs the command.
     *
     * The code to execute is either defined directly with the
     * setCode() method or by overriding the execute() method
     * in a sub-class.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return int The command exit code
     *
     * @throws \Exception
     *
     * @see setCode()
     * @see execute()
     *
     * @api
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        // force the creation of the synopsis before the merge with the app definition
        $this->getSynopsis();

        // add the application arguments and options
        $this->mergeApplicationDefinition();

        // bind the input against the command specific arguments/options
        try {
            $input->bind($this->getDefinition());
        } catch (\Exception $e) {
            if (!$this->ignoreValidationErrors) {
                throw $e;
            }
        }

        $this->initialize($input, $output);

        if (null !== $this->getProcessTitle()) {
            if (function_exists('cli_set_process_title')) {
                cli_set_process_title($this->getProcessTitle());
            } elseif (function_exists('setproctitle')) {
                setproctitle($this->getProcessTitle());
            } elseif (OutputInterface::VERBOSITY_VERY_VERBOSE === $output->getVerbosity()) {
                $output->writeln('<comment>Install the proctitle PECL to be able to change the process title.</comment>');
            }
        }

        if ($input->isInteractive()) {
            $this->interact($input, $output);
        }

        $input->validate();

        if ($this->code) {
            $statusCode = call_user_func($this->code, $input, $output);
        } else {
            $statusCode = $this->execute($input, $output);
        }

        return is_numeric($statusCode) ? (int) $statusCode : 0;
    }

    /**
     * Sets the code to execute when running this command.
     *
     * If this method is used, it overrides the code defined
     * in the execute() method.
     *
     * @param callable $code A callable(InputInterface $input, OutputInterface $output)
     *
     * @return Command The current instance
     *
     * @throws \InvalidArgumentException
     *
     * @see execute()
     *
     * @api
     */
    public function setCode($code)
    {
        if (!is_callable($code)) {
            throw new \InvalidArgumentException('Invalid callable provided to Command::setCode.');
        }

        $this->code = $code;

        return $this;
    }

    /**
     * Merges the application definition with the command definition.
     *
     * This method is not part of public API and should not be used directly.
     *
     * @param bool $mergeArgs Whether to merge or not the Application definition arguments to Command definition arguments
     */
    public function mergeApplicationDefinition($mergeArgs = true)
    {
        if (null === $this->application || (true === $this->applicationDefinitionMerged && ($this->applicationDefinitionMergedWithArgs || !$mergeArgs))) {
            return;
        }

        $definition = $this->getDefinition();

        if ($mergeArgs) {
            $currentArguments = $definition->getArguments();
            $definition->setArguments($this->application->getDefinition()->getArguments());
            $definition->addArguments($currentArguments);
        }

        $definition->addOptions($this->application->getDefinition()->getOptions());

        $this->applicationDefinitionMerged = true;
        if ($mergeArgs) {
            $this->applicationDefinitionMergedWithArgs = true;
        }
    }

    /**
     * Sets an array of argument and option instances.
     *
     * @param array|InputDefinition $definition An array of argument and option instances or a definition instance
     *
     * @return Command The current instance
     *
     * @api
     */
    public function setDefinition($definition)
    {
        parent::setDefinition($definition);

        $this->applicationDefinitionMerged = false;

        return $this;
    }

    /**
     * Gets the InputDefinition to be used to create XML and Text representations of this Command.
     *
     * Can be overridden to provide the original command representation when it would otherwise
     * be changed by merging with the application InputDefinition.
     *
     * This method is not part of public API and should not be used directly.
     *
     * @return InputDefinition An InputDefinition instance
     */
    public function getNativeDefinition()
    {
        return $this->getDefinition();
    }

    /**
     * Gets a helper instance by name.
     *
     * @param string $name The helper name
     *
     * @return mixed The helper value
     *
     * @throws \InvalidArgumentException if the helper is not defined
     *
     * @api
     */
    public function getHelper($name)
    {
        return $this->helperSet->get($name);
    }

    /**
     * Returns a text representation of the command.
     *
     * @return string A string representing the command
     *
     * @deprecated since version 2.3, to be removed in 3.0.
     */
    public function asText()
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.3 and will be removed in 3.0.', E_USER_DEPRECATED);

        $descriptor = new TextDescriptor();
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $descriptor->describe($output, $this, array('raw_output' => true));

        return $output->fetch();
    }

    /**
     * Returns an XML representation of the command.
     *
     * @param bool $asDom Whether to return a DOM or an XML string
     *
     * @return string|\DOMDocument An XML string representing the command
     *
     * @deprecated since version 2.3, to be removed in 3.0.
     */
    public function asXml($asDom = false)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.3 and will be removed in 3.0.', E_USER_DEPRECATED);

        $descriptor = new XmlDescriptor();

        if ($asDom) {
            return $descriptor->getCommandDocument($this);
        }

        $output = new BufferedOutput();
        $descriptor->describe($output, $this);

        return $output->fetch();
    }

    /**
     * Set the configuration for the command.
     *
     * Use this if the configuration was defined separately from the command.
     *
     * @param CommandConfiguration $configuration
     */
    public function setConfiguration(CommandConfiguration $configuration)
    {
        $this->setEnabled($configuration->isEnabled());
        $this->setName($configuration->getName());
        $this->setProcessTitle($configuration->getProcessTitle());
        $this->setAliases($configuration->getAliases());
        parent::setDefinition($configuration->getDefinition());
        $this->setHelp($configuration->getHelp());
        $this->setDescription($configuration->getDescription());
    }
}
