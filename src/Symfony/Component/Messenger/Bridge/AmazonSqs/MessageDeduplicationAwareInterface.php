<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs;

/**
 * @see https://docs.aws.amazon.com/en_gb/AWSSimpleQueueService/latest/SQSDeveloperGuide/using-messagededuplicationid-property.html
 */
interface MessageDeduplicationAwareInterface
{
    /**
     * The max length of MessageDeduplicationId is 128 characters.
     * Valid values: alphanumeric characters and punctuation (!"#$%&'()*+,-./:;<=>?@[]^_`{|}~).
     */
    public function getMessageDeduplicationId(): ?string;
}
