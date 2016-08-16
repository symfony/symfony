<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Exports a profile.
 *
 * @deprecated since version 2.8, to be removed in 3.0.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExportCommand extends Command
{
    private $profiler;

    public function __construct(Profiler $profiler = null)
    {
        $this->profiler = $profiler;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        if (null === $this->profiler) {
            return false;
        }

        return parent::isEnabled();
    }

    protected function configure()
    {
        $this
            ->setName('profiler:export')
            ->setDescription('[DEPRECATED] Exports a profile')
            ->setDefinition(array(
                new InputArgument('token', InputArgument::REQUIRED, 'The profile token'),
            ))
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command exports a profile to the standard output:

  <info>php %command.full_name% profile_token</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = $this->getHelper('formatter');

        $output->writeln($formatter->formatSection('warning', 'The profiler:export command is deprecated since version 2.8 and will be removed in 3.0', 'comment'));

        $token = $input->getArgument('token');

        if (!$profile = $this->profiler->loadProfile($token)) {
            throw new \LogicException(sprintf('Profile with token "%s" does not exist.', $token));
        }

        $output->writeln($this->profiler->export($profile), OutputInterface::OUTPUT_RAW);
    }
}
