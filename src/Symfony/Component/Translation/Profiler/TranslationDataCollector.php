<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Profiler;

use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;
use Symfony\Component\Profiler\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Translation\DataCollectorTranslator;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class TranslationDataCollector implements LateDataCollectorInterface
{
    /**
     * @var DataCollectorTranslator
     */
    private $translator;

    /**
     * @param DataCollectorTranslator $translator
     */
    public function __construct(DataCollectorTranslator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        return new TranslationData($this->translator);
    }
}
