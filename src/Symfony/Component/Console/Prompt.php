<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Console;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A Prompt is used to render a dynamic prompt in Shell object
 *
 */
class Prompt
{

    private $application;

    /**
     * @param Application $application
     */
    public function __construct(Application $application)
    {
        $this->application = $application;
    }

    /**
     * @param Formatter\OutputFormatterInterface $formatter
     *
     * @return string
     */
    public function render(OutputFormatterInterface $formatter)
    {
        return $formatter->format(sprintf("%s > " , $this->application->getName()));
    }
}
