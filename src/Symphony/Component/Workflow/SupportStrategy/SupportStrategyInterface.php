<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Workflow\SupportStrategy;

use Symphony\Component\Workflow\Workflow;

/**
 * @author Andreas Kleemann <akleemann@inviqa.com>
 *
 * @deprecated since Symphony 4.1, use WorkflowSupportStrategyInterface instead
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
