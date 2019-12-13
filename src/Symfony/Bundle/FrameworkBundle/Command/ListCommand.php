<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Command\ListCommand as BaseListCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;

class ListCommand extends ContainerDebugCommand
{
    private $fileLinkFormatter;
    private $listCommand;
    private $supportsHref;
    private $hyperlinkedCommands = [];

    public function __construct(BaseListCommand $listCommand, FileLinkFormatter $fileLinkFormatter = null)
    {
        $this->fileLinkFormatter = $fileLinkFormatter;
        $this->listCommand = $listCommand;
        $this->supportsHref = method_exists(OutputFormatterStyle::class, 'setHref');
        parent::__construct($this->listCommand->getName());
    }

    protected function configure()
    {
        $this
            ->setName($this->listCommand->getName())
            ->setDefinition($this->listCommand->getNativeDefinition())
            ->setDescription($this->listCommand->getDescription())
            ->setHelp($this->listCommand->getHelp())
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->addCommandHyperlinks();

        $this->listCommand->setApplication($this->getApplication());
        return $this->listCommand->execute($input, $output);
    }

    private function getFileLink(string $class): string
    {
        if (null === $this->fileLinkFormatter
            || (null === $r = $this->getContainerBuilder()->getReflectionClass($class, false))) {
            return '';
        }

        return (string) $this->fileLinkFormatter->format($r->getFileName(), $r->getStartLine());
    }

    protected function addCommandHyperlinks(): void
    {
        if (!($this->supportsHref && $this->fileLinkFormatter && $this->getApplication())) {
            return;
        }

        foreach ($this->getApplication()->all() as $command) {
            $id = spl_object_id($command);
            if (isset($this->hyperlinkedCommands[$id])) {
                continue;
            }
            $fileLink = $this->getFileLink(get_class($command));
            if (!$fileLink) {
                continue;
            }
            $command->setDescription(sprintf(
                '<href=%s>^</> %s',
                $fileLink,
                $command->getDescription()
            ));
            $this->hyperlinkedCommands[$id] = $command;
        }
    }
}
