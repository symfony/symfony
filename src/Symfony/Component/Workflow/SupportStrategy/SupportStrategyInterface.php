<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\SupportStrategy;

use Symfony\Component\Workflow\Workflow;

/**
 * @author Andreas Kleemann <akleemann@inviqa.com>
 */
interface SupportStrategyInterface
{
    /**
     * @param Workflow $workflow
     * @param object   $subject
     *
     * @return bool
     */
    public function supports(Workflow $workflow, $subject);
}
