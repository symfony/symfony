<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dotenv\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Dotenv;

/**
 * A console command to debug current dotenv files with variables and values.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DebugCommand extends Command
{
    protected static $defaultName = 'debug:dotenv';
    protected static $defaultDescription = 'Lists all dotenv files with variables and values';

    private $kernelEnvironment;
    private $projectDirectory;

    public function __construct(string $kernelEnvironment, string $projectDirectory)
    {
        $this->kernelEnvironment = $kernelEnvironment;
        $this->projectDirectory = $projectDirectory;

        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Dotenv Variables & Files');

        if (!\array_key_exists('SYMFONY_DOTENV_VARS', $_SERVER)) {
            $io->error('Dotenv component is not initialized.');

            return 1;
        }

        $filePath = $this->projectDirectory.\DIRECTORY_SEPARATOR.'.env';
        $envFiles = $this->getEnvFiles($filePath);
        $availableFiles = array_filter($envFiles, 'is_file');

        if (\in_array(sprintf('%s.local.php', $filePath), $availableFiles, true)) {
            $io->warning('Due to existing dump file (.env.local.php) all other dotenv files are skipped.');
        }

        if (is_file($filePath) && is_file(sprintf('%s.dist', $filePath))) {
            $io->warning(sprintf('The file %s.dist gets skipped due to the existence of %1$s.', $this->getRelativeName($filePath)));
        }

        $io->section('Scanned Files (in descending priority)');
        $io->listing(array_map(function (string $envFile) use ($availableFiles) {
            return \in_array($envFile, $availableFiles, true)
                ? sprintf('<fg=green>✓</> %s', $this->getRelativeName($envFile))
                : sprintf('<fg=red>⨯</> %s', $this->getRelativeName($envFile));
        }, $envFiles));

        $variables = $this->getVariables($availableFiles);

        $io->section('Variables');
        $io->table(
            array_merge(['Variable', 'Value'], array_map([$this, 'getRelativeName'], $availableFiles)),
            $variables
        );

        $io->comment('Note that values might be different between web and CLI.');

        return 0;
    }

    private function getVariables(array $envFiles): array
    {
        $variables = [];
        $fileValues = [];
        $dotenvVars = array_flip(explode(',', $_SERVER['SYMFONY_DOTENV_VARS'] ?? ''));

        foreach ($envFiles as $envFile) {
            $fileValues[$envFile] = $this->loadValues($envFile);
            $variables += $fileValues[$envFile];
        }

        foreach ($variables as $var => $varDetails) {
            $realValue = $_SERVER[$var] ?? '';
            $varDetails = [$var, '<fg=green>'.OutputFormatter::escape($realValue).'</>'];
            $varSeen = !isset($dotenvVars[$var]);

            foreach ($envFiles as $envFile) {
                if (null === $value = $fileValues[$envFile][$var] ?? null) {
                    $varDetails[] = '<fg=yellow>n/a</>';
                    continue;
                }

                $shortenedValue = OutputFormatter::escape($this->getHelper('formatter')->truncate($value, 30));
                $varDetails[] = $value === $realValue && !$varSeen ? '<fg=green>'.$shortenedValue.'</>' : $shortenedValue;
                $varSeen = $varSeen || $value === $realValue;
            }

            $variables[$var] = $varDetails;
        }

        ksort($variables);

        return $variables;
    }

    private function getEnvFiles(string $filePath): array
    {
        $files = [
            sprintf('%s.local.php', $filePath),
            sprintf('%s.%s.local', $filePath, $this->kernelEnvironment),
            sprintf('%s.%s', $filePath, $this->kernelEnvironment),
        ];

        if ('test' !== $this->kernelEnvironment) {
            $files[] = sprintf('%s.local', $filePath);
        }

        if (!is_file($filePath) && is_file(sprintf('%s.dist', $filePath))) {
            $files[] = sprintf('%s.dist', $filePath);
        } else {
            $files[] = $filePath;
        }

        return $files;
    }

    private function getRelativeName(string $filePath): string
    {
        if (str_starts_with($filePath, $this->projectDirectory)) {
            return substr($filePath, \strlen($this->projectDirectory) + 1);
        }

        return basename($filePath);
    }

    private function loadValues(string $filePath): array
    {
        if (str_ends_with($filePath, '.php')) {
            return include $filePath;
        }

        return (new Dotenv())->parse(file_get_contents($filePath));
    }
}
