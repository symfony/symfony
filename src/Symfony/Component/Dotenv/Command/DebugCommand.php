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

        $dotenvDirectory = $this->getDotenvDirectory();
        $envFiles = $this->getEnvFiles($dotenvDirectory);
        $availableFiles = array_filter($envFiles, function (string $file) use ($dotenvDirectory) {
            return is_file($this->getFilePath($dotenvDirectory, $file));
        });

        if (\in_array('.env.local.php', $availableFiles, true)) {
            $io->warning('Due to existing dump file (.env.local.php) all other dotenv files are skipped.');
        }

        if (is_file($this->getFilePath($dotenvDirectory, '.env')) && is_file($this->getFilePath($dotenvDirectory, '.env.dist'))) {
            $io->warning('The file .env.dist gets skipped due to the existence of .env.');
        }

        $io->section('Scanned Files (in descending priority)');
        $io->listing(array_map(static function (string $envFile) use ($availableFiles, $dotenvDirectory) {
            $file = (null === $dotenvDirectory ? '' : $dotenvDirectory.\DIRECTORY_SEPARATOR).$envFile;

            return \in_array($envFile, $availableFiles, true)
                ? sprintf('<fg=green>✓</> %s', $file)
                : sprintf('<fg=red>⨯</> %s', $file);
        }, $envFiles));

        $io->section('Variables');
        $io->table(
            array_merge(['Variable', 'Value'], $availableFiles),
            $this->getVariables($dotenvDirectory, $availableFiles)
        );

        $io->comment('Note real values might be different between web and CLI.');

        return 0;
    }

    private function getVariables(?string $dotenvDirectory, array $envFiles): array
    {
        $vars = explode(',', $_SERVER['SYMFONY_DOTENV_VARS'] ?? '');
        sort($vars);

        $output = [];
        $fileValues = [];
        foreach ($vars as $var) {
            $realValue = $_SERVER[$var];
            $varDetails = [$var, $realValue];
            foreach ($envFiles as $envFile) {
                $values = $fileValues[$envFile] ?? $fileValues[$envFile] = $this->loadValues($dotenvDirectory, $envFile);

                $varString = $values[$var] ?? '<fg=yellow>n/a</>';
                $shortenedVar = $this->getHelper('formatter')->truncate($varString, 30);
                $varDetails[] = $varString === $realValue ? '<fg=green>'.$shortenedVar.'</>' : $shortenedVar;
            }

            $output[] = $varDetails;
        }

        return $output;
    }

    private function getEnvFiles(?string $dotenvDirectory): array
    {
        $files = [
            '.env.local.php',
            sprintf('.env.%s.local', $this->kernelEnvironment),
            sprintf('.env.%s', $this->kernelEnvironment),
        ];

        if ('test' !== $this->kernelEnvironment) {
            $files[] = '.env.local';
        }

        if (!is_file($this->getFilePath($dotenvDirectory, '.env')) && is_file($this->getFilePath($dotenvDirectory, '.env.dist'))) {
            $files[] = '.env.dist';
        } else {
            $files[] = '.env';
        }

        return $files;
    }

    private function getFilePath(?string $dotenvDirectory, string $file): string
    {
        return $this->projectDirectory.(null === $dotenvDirectory ? '' : \DIRECTORY_SEPARATOR.$dotenvDirectory).\DIRECTORY_SEPARATOR.$file;
    }

    private function loadValues(?string $dotenvDirectory, string $file): array
    {
        $filePath = $this->getFilePath($dotenvDirectory, $file);

        if (str_ends_with($filePath, '.php')) {
            return include $filePath;
        }

        return (new Dotenv())->parse(file_get_contents($filePath));
    }

    private function getDotenvDirectory(): ?string
    {
        $projectDir = is_file($projectDir = $this->projectDirectory) ? basename($projectDir) : $projectDir;

        $composerFile = $projectDir.'/composer.json';
        if (is_file($composerFile)) {
            $composerContent = json_decode(file_get_contents($composerFile), true);
            $dotenvPath = $composerContent['extra']['runtime']['dotenv_path'] ?? null;

            if (null !== $dotenvPath) {
                return \dirname($dotenvPath);
            }
        }

        return null;
    }
}
