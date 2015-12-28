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
 * Imports a profile.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ImportCommand extends Command
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
            ->setName('profiler:import')
            ->setDescription('Imports a profile')
            ->setDefinition(array(
                new InputArgument('filename', InputArgument::OPTIONAL, 'The profile path'),
            ))
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command imports a profile:

  <info>php %command.full_name% profile_filepath</info>

You can also pipe the profile via STDIN:

  <info>cat profile_file | php %command.full_name%</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $data = '';
        if ($input->getArgument('filename')) {
            $data = file_get_contents($input->getArgument('filename'));
        } else {
            if (0 !== ftell(STDIN)) {
                throw new \RuntimeException('Please provide a filename or pipe the profile to STDIN.');
            }

            while (!feof(STDIN)) {
                $data .= fread(STDIN, 1024);
            }
        }

        if (!$profile = $this->profiler->import($data)) {
            throw new \LogicException('The profile already exists in the database.');
        }

        $output->writeln(sprintf('Profile "%s" has been successfully imported.', $profile->getToken()));
    }
}
