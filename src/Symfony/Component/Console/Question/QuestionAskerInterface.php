<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Question;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Contract for question askers.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
interface QuestionAskerInterface
{
    /**
     * Asks a question to the user.
     *
     * @param OutputInterface $output
     * @param resource        $inputStream
     *
     * @return string The user answer
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function ask(OutputInterface $output, $inputStream);
}
