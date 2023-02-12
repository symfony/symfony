<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Command;

use Symfony\Bridge\Twig\Command\LintCommand as BaseLintCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Command that will validate your template syntax and output encountered errors.
 *
 * @author Marc Weistroff <marc.weistroff@sensiolabs.com>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
#[AsCommand(name: 'lint:twig', description: 'Lint a Twig template and outputs encountered errors')]
final class LintCommand extends BaseLintCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setHelp(
                $this->getHelp().<<<'EOF'

Or all template files in a bundle:

  <info>php %command.full_name% @AcmeDemoBundle</info>

EOF
            )
        ;
    }

    protected function findFiles(string $filename): iterable
    {
        if (str_starts_with($filename, '@')) {
            $filename = $this->getApplication()->getKernel()->locateResource($filename);
        }

        return parent::findFiles($filename);
    }
}
