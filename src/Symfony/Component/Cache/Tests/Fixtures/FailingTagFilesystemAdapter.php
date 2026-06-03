<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Fixtures;

use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;

/**
 * Adapter that simulates a failure when saving tags.
 */
class FailingTagFilesystemAdapter extends FilesystemTagAwareAdapter
{
    protected function doSave(array $values, int $lifetime, array $addTagData = [], array $removeTagData = []): array
    {
        $failed = parent::doSave($values, $lifetime, $addTagData, $removeTagData);

        // Simulate tag save failure by returning tag IDs as failed
        foreach ($addTagData as $tagId => $ids) {
            $failed[] = $tagId;
        }

        return $failed;
    }
}
