<?php

namespace Symfony\Framework\WebBundle\Console;

use Symfony\Components\Console\Application as BaseApplication;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Foundation\Kernel;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * 
 *
 * @package    symfony
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Application extends BaseApplication
{
  protected $kernel;

  /**
   * Constructor.
   */
  public function __construct(Kernel $kernel)
  {
    $this->kernel = $kernel;

    parent::__construct('Symfony', Kernel::VERSION.' - '.$kernel->getName());

    $this->definition->addOption(new InputOption('--shell', '-s', InputOption::PARAMETER_NONE, 'Launch the shell.'));

    $this->kernel->boot();

    $this->registerCommands();
  }

  /**
   * Gets the Kernel associated with this Console.
   *
   * @return Kernel A Kernel instance
   */
  public function getKernel()
  {
    return $this->kernel;
  }

  /**
   * Runs the current application.
   *
   * @param InputInterface  $input  An Input instance
   * @param OutputInterface $output An Output instance
   *
   * @return integer 0 if everything went fine, or an error code
   */
  public function doRun(InputInterface $input, OutputInterface $output)
  {
    if (true === $input->hasParameterOption(array('--shell', '-s')))
    {
      $shell = new Shell($this);
      $shell->run();

      return 0;
    }

    return parent::doRun($input, $output);
  }

  protected function registerCommands()
  {
    // search all places where there are bundles
    foreach ($this->kernel->getContainer()->getParameter('kernel.bundle_dirs') as $dir)
    {
      // search all registered bundles
      foreach ($this->kernel->getBundles() as $bundle)
      {
        $bundleBase = dirname(str_replace('\\', '/', get_class($bundle)));
        $commandDir = $dir.'/'.basename($bundleBase).'/Command';
        if (!is_dir($commandDir))
        {
          continue;
        }

        // look for commands
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($commandDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file)
        {
          if ($file->isDir())
          {
            continue;
          }

          $class = str_replace('/', '\\', $bundleBase).'\\Command\\'.str_replace(realpath($commandDir).'/', '', basename(realpath($file), '.php'));

          $r = new \ReflectionClass($class);

          if ($r->isSubclassOf('Symfony\\Components\\Console\\Command\\Command') && !$r->isAbstract())
          {
            $this->addCommand(new $class());
          }
        }
      }
    }
  }
}
