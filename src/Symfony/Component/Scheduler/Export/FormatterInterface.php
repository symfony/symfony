<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Export;

use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface FormatterInterface
{
    /**
     * The formatter should return a string once the desired format has been used.
     *
     * The structure of the formatted tasks depends on the format used but it SHOULD at least contain:
     *  - The name of the tasks
     *  - The expression of the tasks
     *  - The scheduled_at date
     *  - The last_execution date
     */
    public function format(TaskInterface $task): string;

    /**
     * Define if the formatter can handle the desired format.
     */
    public function support(string $format): bool;
}
