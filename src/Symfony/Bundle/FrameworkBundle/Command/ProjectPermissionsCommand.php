<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\LogicException;

/**
 * Fixes Symfony2 project directory permissions.
 *
 * @author Serg N. Kalachev <serg@kalachev.ru>
 */
class ProjectPermissionsCommand extends AbstractConfigCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('project:permissions')
            ->setDescription('Fixes Symfony2 project directory permissions')
            ->setHelp('The <info>%command.name%</info> command fixes cache and logs directories permissions.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws ProcessFailedException, LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            throw new LogicException('Windows is NOT supported.');
        }
        $cacheDir = realpath($this->getContainer()->getParameter('kernel.cache_dir').'/..');
        if ($cacheDir === false) {
            throw new LogicException('Oops! Invalid kernel.cache_dir parameter.');
        }
        $logsDir = realpath($this->getContainer()->getParameter('kernel.logs_dir'));
        if ($logsDir === false) {
            throw new LogicException('Oops! Invalid kernel.logs_dir parameter.');
        }
        $process = new Process("sudo rm -rf $cacheDir/*");
        $process->mustRun();
        $process->setCommandLine("sudo rm -rf $logsDir/*")->mustRun();
        $process->setCommandLine("ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1");
        $www_user_name = trim($process->mustRun()->getOutput());
        if (empty($www_user_name)) {
            throw new LogicException("Can't find web-server's username. Try to start your web server.");
        }
        $current_user_name = trim($process->setCommandLine('whoami')->mustRun()->getOutput());
        if (empty($current_user_name)) {
            throw new LogicException("Can't detect your username. Is command 'whoami' available ?");
        }
        $exit_code = $process->setCommandLine('sudo chmod +a "'.$www_user_name.' allow delete,write,append,file_inherit,directory_inherit" '.$cacheDir.' '.$logsDir)->run();
        if (!$exit_code) {
            // success, chmod +a is supported (e.g. Mac OS X)
            $process->setCommandLine('sudo chmod +a "'.$current_user_name.' allow delete,write,append,file_inherit,directory_inherit" '.$cacheDir.' '.$logsDir)->mustRun();
        } elseif (1 == $exit_code) {
            // chmod +a is NOT supported (e.g. Debian)
            $exit_code = $process->setCommandLine('setfacl')->run();
            if (127 == $exit_code) {
                throw new LogicException("The program 'setfacl' is currently not installed. You MUST install it by typing:\nsudo apt-get install acl");
            } elseif (2 == $exit_code) {
                // setfacl is installed, wrong arguments, proceed setfacl commands
                $process->setCommandLine('sudo setfacl -R -m u:"'.$www_user_name.'":rwX -m u:'.$current_user_name.':rwX '.$cacheDir.' '.$logsDir)->mustRun();
                $process->setCommandLine('sudo setfacl -dR -m u:"'.$www_user_name.'":rwX -m u:'.$current_user_name.':rwX '.$cacheDir.' '.$logsDir)->mustRun();
            } else {
                // unknown exit code. Houston, we have a problem!
                throw new ProcessFailedException($process);
            }
        } else {
            throw new ProcessFailedException($process);
        }
        $output->writeLn('All Done.');
    }
}
