<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\VarDumper\Dumper\CliDumper;

if (Logger::API >= 3) {
    /**
     * The base class for compatibility between Monolog 3 LogRecord and Monolog 1/2 array records.
     *
     * @author Jordi Boggiano <j.boggiano@seld.be>
     *
     * @internal
     */
    trait CompatibilityIsHandlingHandler
    {
        abstract private function doIsHandling(array|LogRecord $record): bool;

        /**
         * {@inheritdoc}
         */
        public function isHandling(LogRecord $record): bool
        {
            return $this->doIsHandling($record);
        }
    }
} else {
    /**
     * The base class for compatibility between Monolog 3 LogRecord and Monolog 1/2 array records.
     *
     * @author Jordi Boggiano <j.boggiano@seld.be>
     *
     * @internal
     */
    trait CompatibilityIsHandlingHandler
    {
        abstract private function doIsHandling(array|LogRecord $record): bool;

        /**
         * {@inheritdoc}
         */
        public function isHandling(array $record): bool
        {
            return $this->doIsHandling($record);
        }
    }
}

/**
 * Writes logs to the console output depending on its verbosity setting.
 *
 * It is disabled by default and gets activated as soon as a command is executed.
 * Instead of listening to the console events, the output can also be set manually.
 *
 * The minimum logging level at which this handler will be triggered depends on the
 * verbosity setting of the console output. The default mapping is:
 * - OutputInterface::VERBOSITY_NORMAL will show all WARNING and higher logs
 * - OutputInterface::VERBOSITY_VERBOSE (-v) will show all NOTICE and higher logs
 * - OutputInterface::VERBOSITY_VERY_VERBOSE (-vv) will show all INFO and higher logs
 * - OutputInterface::VERBOSITY_DEBUG (-vvv) will show all DEBUG and higher logs, i.e. all logs
 *
 * This mapping can be customized with the $verbosityLevelMap constructor parameter.
 *
 * @author Tobias Schultze <http://tobion.de>
 *
 * @final since Symfony 6.1
 */
class ConsoleHandler extends AbstractProcessingHandler implements EventSubscriberInterface
{
    use CompatibilityHandler;
    use CompatibilityIsHandlingHandler;
    use CompatibilityProcessingHandler;

    private ?OutputInterface $output;
    private array $verbosityLevelMap = [
        OutputInterface::VERBOSITY_QUIET => Logger::ERROR,
        OutputInterface::VERBOSITY_NORMAL => Logger::WARNING,
        OutputInterface::VERBOSITY_VERBOSE => Logger::NOTICE,
        OutputInterface::VERBOSITY_VERY_VERBOSE => Logger::INFO,
        OutputInterface::VERBOSITY_DEBUG => Logger::DEBUG,
    ];
    private array $consoleFormatterOptions;

    /**
     * @param OutputInterface|null $output            The console output to use (the handler remains disabled when passing null
     *                                                until the output is set, e.g. by using console events)
     * @param bool                 $bubble            Whether the messages that are handled can bubble up the stack
     * @param array                $verbosityLevelMap Array that maps the OutputInterface verbosity to a minimum logging
     *                                                level (leave empty to use the default mapping)
     */
    public function __construct(OutputInterface $output = null, bool $bubble = true, array $verbosityLevelMap = [], array $consoleFormatterOptions = [])
    {
        parent::__construct(Logger::DEBUG, $bubble);
        $this->output = $output;

        if ($verbosityLevelMap) {
            $this->verbosityLevelMap = $verbosityLevelMap;
        }

        $this->consoleFormatterOptions = $consoleFormatterOptions;
    }

    /**
     * {@inheritdoc}
     */
    private function doIsHandling(array|LogRecord $record): bool
    {
        return $this->updateLevel() && parent::isHandling($record);
    }

    /**
     * {@inheritdoc}
     */
    private function doHandle(array|LogRecord $record): bool
    {
        // we have to update the logging level each time because the verbosity of the
        // console output might have changed in the meantime (it is not immutable)
        return $this->updateLevel() && parent::handle($record);
    }

    /**
     * Sets the console output to use for printing logs.
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Disables the output.
     */
    public function close(): void
    {
        $this->output = null;

        parent::close();
    }

    /**
     * Before a command is executed, the handler gets activated and the console output
     * is set in order to know where to write the logs.
     */
    public function onCommand(ConsoleCommandEvent $event)
    {
        $output = $event->getOutput();
        if ($output instanceof ConsoleOutputInterface) {
            $output = $output->getErrorOutput();
        }

        $this->setOutput($output);
    }

    /**
     * After a command has been executed, it disables the output.
     */
    public function onTerminate(ConsoleTerminateEvent $event)
    {
        $this->close();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onCommand', 255],
            ConsoleEvents::TERMINATE => ['onTerminate', -255],
        ];
    }

    private function doWrite(array|LogRecord $record): void
    {
        // at this point we've determined for sure that we want to output the record, so use the output's own verbosity
        $this->output->write((string) $record['formatted'], false, $this->output->getVerbosity());
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        if (!class_exists(CliDumper::class)) {
            return new LineFormatter();
        }
        if (!$this->output) {
            return new ConsoleFormatter($this->consoleFormatterOptions);
        }

        return new ConsoleFormatter(array_replace([
            'colors' => $this->output->isDecorated(),
            'multiline' => OutputInterface::VERBOSITY_DEBUG <= $this->output->getVerbosity(),
        ], $this->consoleFormatterOptions));
    }

    /**
     * Updates the logging level based on the verbosity setting of the console output.
     *
     * @return bool Whether the handler is enabled and verbosity is not set to quiet
     */
    private function updateLevel(): bool
    {
        if (null === $this->output) {
            return false;
        }

        $verbosity = $this->output->getVerbosity();
        if (isset($this->verbosityLevelMap[$verbosity])) {
            $this->setLevel($this->verbosityLevelMap[$verbosity]);
        } else {
            $this->setLevel(Logger::DEBUG);
        }

        return true;
    }
}
