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

        $dotenvFile = $this->getDotenvFile();
        $envFiles = $this->getEnvFiles($dotenvFile);
        $availableFiles = array_filter($envFiles, function (string $file) {
            return is_file($this->getFilePath($file));
        });

        $localDotenvFile = sprintf('%s.local.php', $dotenvFile);
        if (\in_array($localDotenvFile, $availableFiles, true)) {
            $io->warning(sprintf('Due to existing dump file (%s) all other dotenv files are skipped.', $localDotenvFile));
        }

        $distDotenvFile = sprintf('%s.dist', $dotenvFile);
        if (is_file($this->getFilePath($dotenvFile)) && is_file($this->getFilePath($distDotenvFile))) {
            $io->warning(sprintf('The file %s gets skipped due to the existence of %s.', $distDotenvFile, $dotenvFile));
        }

        $io->section('Scanned Files (in descending priority)');
        $io->listing(array_map(static function (string $envFile) use ($availableFiles) {
            return \in_array($envFile, $availableFiles, true)
                ? sprintf('<fg=green>✓</> %s', $envFile)
                : sprintf('<fg=red>⨯</> %s', $envFile);
        }, $envFiles));

        $io->section('Variables');
        $io->table(
            array_merge(['Variable', 'Value'], array_map('basename', $availableFiles)),
            $this->getVariables($availableFiles)
        );

        $io->comment('Note real values might be different between web and CLI.');

        return 0;
    }

    private function getVariables(array $envFiles): array
    {
        $vars = explode(',', $_SERVER['SYMFONY_DOTENV_VARS'] ?? '');
        sort($vars);

        $output = [];
        $fileValues = [];
        foreach ($vars as $var) {
            $realValue = $_SERVER[$var];
            $varDetails = [$var, $realValue];
            foreach ($envFiles as $envFile) {
                $values = $fileValues[$envFile] ?? $fileValues[$envFile] = $this->loadValues($envFile);

                $varString = $values[$var] ?? '<fg=yellow>n/a</>';
                $shortenedVar = $this->getHelper('formatter')->truncate($varString, 30);
                $varDetails[] = $varString === $realValue ? '<fg=green>'.$shortenedVar.'</>' : $shortenedVar;
            }

            $output[] = $varDetails;
        }

        return $output;
    }

    private function getEnvFiles(string $dotenvFile): array
    {
        $files = [
            sprintf('%s.local.php', $dotenvFile),
            sprintf('%s.%s.local', $dotenvFile, $this->kernelEnvironment),
            sprintf('%s.%s', $dotenvFile, $this->kernelEnvironment),
        ];

        if ('test' !== $this->kernelEnvironment) {
            $files[] = sprintf('%s.local', $dotenvFile);
        }

        if (!is_file($this->getFilePath($dotenvFile)) && is_file($this->getFilePath($distDotenvFile = sprintf('%s.dist', $dotenvFile)))) {
            $files[] = $distDotenvFile;
        } else {
            $files[] = $dotenvFile;
        }

        return $files;
    }

    private function getFilePath(string $file): string
    {
        return $this->projectDirectory.\DIRECTORY_SEPARATOR.$file;
    }

    private function loadValues(string $file): array
    {
        $filePath = $this->getFilePath($file);

        if (str_ends_with($filePath, '.php')) {
            return include $filePath;
        }

        return (new Dotenv())->parse(file_get_contents($filePath));
    }

    private function getDotenvFile(): string
    {
        $projectDir = is_file($projectDir = $this->projectDirectory) ? basename($projectDir) : $projectDir;

        $composerFile = $projectDir.'/composer.json';
        if (is_file($composerFile)) {
            $composerContent = json_decode(file_get_contents($composerFile), true);

            return $composerContent['extra']['runtime']['dotenv_path'] ?? '.env';
        }

        return '.env';
    }
}
