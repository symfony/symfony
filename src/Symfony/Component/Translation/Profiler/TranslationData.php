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

use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;
use Symfony\Component\Translation\DataCollectorTranslator;

/**
 * TranslationData.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class TranslationData implements ProfileDataInterface
{
    private $counters;
    private $messages;

    public function __construct(DataCollectorTranslator $translator)
    {
        $messages = $this->sanitizeCollectedMessages($translator->getCollectedMessages());

        $this->counters = $this->computeCount($messages);
        $this->messages = $messages;
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @return int
     */
    public function getCountMissings()
    {
        return isset($this->counters[DataCollectorTranslator::MESSAGE_MISSING]) ? $this->counters[DataCollectorTranslator::MESSAGE_MISSING] : 0;
    }

    /**
     * @return int
     */
    public function getCountFallbacks()
    {
        return isset($this->counters[DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK]) ? $this->counters[DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK] : 0;
    }

    /**
     * @return int
     */
    public function getCountDefines()
    {
        return isset($this->counters[DataCollectorTranslator::MESSAGE_DEFINED]) ? $this->counters[DataCollectorTranslator::MESSAGE_DEFINED] : 0;
    }

    private function sanitizeCollectedMessages($messages)
    {
        $result = array();
        foreach ($messages as $key => $message) {
            $messageId = $message['locale'].$message['domain'].$message['id'];

            if (!isset($result[$messageId])) {
                $message['count'] = 1;
                $messages[$key]['translation'] = $this->sanitizeString($message['translation']);
                $result[$messageId] = $message;
            } else {
                $result[$messageId]['count']++;
            }

            unset($messages[$key]);
        }

        return $result;
    }

    private function computeCount($messages)
    {
        $count = array(
            DataCollectorTranslator::MESSAGE_DEFINED => 0,
            DataCollectorTranslator::MESSAGE_MISSING => 0,
            DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK => 0,
        );

        foreach ($messages as $message) {
            ++$count[$message['state']];
        }

        return $count;
    }

    private function sanitizeString($string, $length = 80)
    {
        $string = trim(preg_replace('/\s+/', ' ', $string));

        if (function_exists('mb_strlen') && false !== $encoding = mb_detect_encoding($string)) {
            if (mb_strlen($string, $encoding) > $length) {
                return mb_substr($string, 0, $length - 3, $encoding).'...';
            }
        } elseif (strlen($string) > $length) {
            return substr($string, 0, $length - 3).'...';
        }

        return $string;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'translation';
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return serialize(array('counters' => $this->counters, 'messages' => $this->messages));
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);
        $this->counters = $unserialized['counters'];
        $this->messages = $unserialized['messages'];
    }
}