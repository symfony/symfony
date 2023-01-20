<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Internal;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Runtime\SymfonyRuntime;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class ComposerPlugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    private static $activated = false;

    public function activate(Composer $composer, IOInterface $io): void
    {
        self::$activated = true;
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        self::$activated = false;
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        @unlink($composer->getConfig()->get('vendor-dir').'/autoload_runtime.php');
    }

    public function updateAutoloadFile(): void
    {
        $vendorDir = realpath($this->composer->getConfig()->get('vendor-dir'));

        if (!is_file($autoloadFile = $vendorDir.'/autoload.php')
            || false === $extra = $this->composer->getPackage()->getExtra()['runtime'] ?? []
        ) {
            return;
        }

        $fs = new Filesystem();
        $projectDir = \dirname(realpath(Factory::getComposerFile()));

        if (null === $autoloadTemplate = $extra['autoload_template'] ?? null) {
            $autoloadTemplate = __DIR__.'/autoload_runtime.template';
        } else {
            if (!$fs->isAbsolutePath($autoloadTemplate)) {
                $autoloadTemplate = $projectDir.'/'.$autoloadTemplate;
            }

            if (!is_file($autoloadTemplate)) {
                throw new \InvalidArgumentException(sprintf('File "%s" defined under "extra.runtime.autoload_template" in your composer.json file not found.', $this->composer->getPackage()->getExtra()['runtime']['autoload_template']));
            }
        }

        $projectDir = $fs->makePathRelative($projectDir, $vendorDir);
        $nestingLevel = 0;

        while (str_starts_with($projectDir, '../')) {
            ++$nestingLevel;
            $projectDir = substr($projectDir, 3);
        }

        if (!$nestingLevel) {
            $projectDir = '__'.'DIR__.'.var_export('/'.$projectDir, true);
        } else {
            $projectDir = 'dirname(__'."DIR__, $nestingLevel)".('' !== $projectDir ? '.'.var_export('/'.$projectDir, true) : '');
        }

        $runtimeClass = $extra['class'] ?? SymfonyRuntime::class;

        unset($extra['class'], $extra['autoload_template']);

        $code = strtr(file_get_contents($autoloadTemplate), [
            '%project_dir%' => $projectDir,
            '%runtime_class%' => var_export($runtimeClass, true),
            '%runtime_options%' => '['.substr(var_export($extra, true), 7, -1)."  'project_dir' => {$projectDir},\n]",
        ]);

        file_put_contents(substr_replace($autoloadFile, '_runtime', -4, 0), $code);
    }

    public static function getSubscribedEvents(): array
    {
        if (!self::$activated) {
            return [];
        }

        return [
            ScriptEvents::POST_AUTOLOAD_DUMP => 'updateAutoloadFile',
        ];
    }
}
