<?php

namespace Symfony\Framework\PropelBundle\Command;

use Symfony\Components\Console\Command\Command;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Framework\WebBundle\Util\Filesystem;
use Symfony\Components\Finder\Finder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * BuildCommand.
 *
 * @package    Symfony
 * @subpackage Framework_PropelBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BuildCommand extends Command
{
    protected $additionalPhingArgs = array();

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('--classes', '', InputOption::PARAMETER_NONE, 'Build all classes'),
            ))
            ->setName('propel:build')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->callPhing('om');

        if (!is_dir($input->getArgument('target'))) {
            throw new \InvalidArgumentException(sprintf('The target directory "%s" does not exist.', $input->getArgument('target')));
        }

        $filesystem = new Filesystem();

        $dirs = $this->container->getKernelService()->getBundleDirs();
        foreach ($this->container->getKernelService()->getBundles() as $bundle) {
            $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
            $namespace = str_replace('/', '\\', dirname($tmp));
            $class = basename($tmp);

            if (isset($dirs[$namespace]) && is_dir($originDir = $dirs[$namespace].'/'.$class.'/Resources/public')) {
                $output->writeln(sprintf('Installing assets for <comment>%s\\%s</comment>', $namespace, $class));

                $targetDir = $input->getArgument('target').'/bundles/'.preg_replace('/bundle$/', '', strtolower($class));

                $filesystem->remove($targetDir);
                mkdir($targetDir, 0755, true);
                $filesystem->mirror($originDir, $targetDir);
            }
        }
    }

    protected function callPhing($taskName, $properties = array())
    {
        $kernel = $this->application->getKernel();

        $tmpDir = sys_get_temp_dir().'/propel-gen';
        $filesystem = new Filesystem();
        $filesystem->remove($tmpDir);
        $filesystem->mkdirs($tmpDir);

        $bundleDirs = $kernel->getBundleDirs();
        foreach ($kernel->getBundles() as $bundle) {
            $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
            $namespace = str_replace('/', '\\', dirname($tmp));
            $class = basename($tmp);

            if (isset($bundleDirs[$namespace]) && is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Resources/config')) {
                $finder = new Finder();
                $schemas = $finder->files()->name('*schema.xml')->followLinks()->in($dir);

                $parts = explode(DIRECTORY_SEPARATOR, realpath($bundleDirs[$namespace]));
                $prefix = implode('.', array_slice($parts, 1, -1));

                foreach ($schemas as $schema) {
                    $filesystem->copy((string) $schema, $file = $tmpDir.DIRECTORY_SEPARATOR.md5($schema).'_'.$schema->getBaseName());

                    $content = file_get_contents($file);
                    $content = preg_replace_callback('/package\s*=\s*"(.*?)"/', function ($matches) use ($prefix) {
                        return sprintf('package="%s"', $prefix.'.'.$matches[1]);
                    }, $content);

                    file_put_contents($file, $content);
                }
            }
        }

        $filesystem->touch($tmpDir.'/build.properties');

        $args = array();
//        $bufferPhingOutput = !$this->commandApplication->withTrace();

        $properties = array_merge(array(
            'propel.database'   => 'mysql',
            'project.dir'       => $tmpDir,
            'propel.output.dir' => $kernel->getRootDir().'/propel',
            'propel.php.dir'    => '/',
        ), $properties);
        foreach ($properties as $key => $value) {
            $args[] = "-D$key=$value";
        }

        // Build file
        $args[] = '-f';
        $args[] = realpath($kernel->getContainer()->getParameter('propel.generator.path').DIRECTORY_SEPARATOR.'build.xml');

/*
        // Logger
        if (DIRECTORY_SEPARATOR != '\\' && (function_exists('posix_isatty') && @posix_isatty(STDOUT))) {
            $args[] = '-logger';
            $args[] = 'phing.listener.AnsiColorLogger';
        }

        // Add our listener to detect errors
        $args[] = '-listener';
        $args[] = 'sfPhingListener';
*/
        // Add any arbitrary arguments last
        foreach ($this->additionalPhingArgs as $arg) {
            if (in_array($arg, array('verbose', 'debug'))) {
                $bufferPhingOutput = false;
            }

            $args[] = '-'.$arg;
        }

        $args[] = $taskName;

        // enable output buffering
        Phing::setOutputStream(new \OutputStream(fopen('php://output', 'w')));
        Phing::startup();
        Phing::setProperty('phing.home', getenv('PHING_HOME'));

//      $this->logSection('propel', 'Running "'.$taskName.'" phing task');

        $bufferPhingOutput = false;
        if ($bufferPhingOutput) {
            ob_start();
        }

        $m = new Phing();
        $m->execute($args);
        $m->runBuild();

        if ($bufferPhingOutput) {
            ob_end_clean();
        }
        print $bufferPhingOutput;
        chdir($kernel->getRootDir());
/*
        // any errors?
        $ret = true;
        if (sfPhingListener::hasErrors())
        {
            $messages = array('Some problems occurred when executing the task:');

            foreach (sfPhingListener::getExceptions() as $exception)
            {
              $messages[] = '';
              $messages[] = preg_replace('/^.*build\-propel\.xml/', 'build-propel.xml', $exception->getMessage());
              $messages[] = '';
            }

            if (count(sfPhingListener::getErrors()))
            {
              $messages[] = 'If the exception message is not clear enough, read the output of the task for';
              $messages[] = 'more information';
            }

            $this->logBlock($messages, 'ERROR_LARGE');

            $ret = false;
        }
*/

        $ret = true;
        return $ret;
    }

    protected function getPhingPropertiesForConnection($databaseManager, $connection)
    {
        $database = $databaseManager->getDatabase($connection);

        return array(
            'propel.database'          => $database->getParameter('phptype'),
            'propel.database.driver'   => $database->getParameter('phptype'),
            'propel.database.url'      => $database->getParameter('dsn'),
            'propel.database.user'     => $database->getParameter('username'),
            'propel.database.password' => $database->getParameter('password'),
            'propel.database.encoding' => $database->getParameter('encoding'),
        );
    }

    protected function getProperties($file)
    {
        $properties = array();

        if (false === $lines = @file($file)) {
            throw new sfCommandException('Unable to parse contents of the "sqldb.map" file.');
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ('' == $line) {
                continue;
            }

            if (in_array($line[0], array('#', ';'))) {
                continue;
            }

            $pos = strpos($line, '=');
            $properties[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
        }

        return $properties;
    }
}
