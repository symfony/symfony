<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Exception\LogicException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\Traits\FilesystemTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Stores tag id <> cache id relationship as a symlink, and lookup on invalidation calls.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author André Rømcke <andre.romcke+symfony@gmail.com>
 *
 * @experimental in 4.3
 */
class FilesystemTagAwareAdapter extends AbstractTagAwareAdapter implements PruneableInterface
{
    use FilesystemTrait {
        doSave as doSaveCache;
        doDelete as doDeleteCache;
    }

    /**
     * Folder used for tag symlinks.
     */
    private const TAG_FOLDER = 'tags';

    /**
     * @var Filesystem|null
     */
    private $fs;

    public function __construct(string $namespace = '', int $defaultLifetime = 0, string $directory = null, MarshallerInterface $marshaller = null)
    {
        $this->marshaller = $marshaller ?? new DefaultMarshaller();
        parent::__construct('', $defaultLifetime);
        $this->init($namespace, $directory);
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, ?int $lifetime, array $addTagData = [], array $removeTagData = []): array
    {
        $failed = $this->doSaveCache($values, $lifetime);

        $fs = $this->getFilesystem();
        // Add Tags as symlinks
        foreach ($addTagData as $tagId => $ids) {
            $tagFolder = $this->getTagFolder($tagId);
            foreach ($ids as $id) {
                if ($failed && \in_array($id, $failed, true)) {
                    continue;
                }

                $file = $this->getFile($id);
                $fs->symlink($file, $this->getFile($id, true, $tagFolder));
            }
        }

        // Unlink removed Tags
        $files = [];
        foreach ($removeTagData as $tagId => $ids) {
            $tagFolder = $this->getTagFolder($tagId);
            foreach ($ids as $id) {
                if ($failed && \in_array($id, $failed, true)) {
                    continue;
                }

                $files[] = $this->getFile($id, false, $tagFolder);
            }
        }
        $fs->remove($files);

        return $failed;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids, array $tagData = []): bool
    {
        $ok = $this->doDeleteCache($ids);

        // Remove tags
        $files = [];
        $fs = $this->getFilesystem();
        foreach ($tagData as $tagId => $idMap) {
            $tagFolder = $this->getTagFolder($tagId);
            foreach ($idMap as $id) {
                $files[] = $this->getFile($id, false, $tagFolder);
            }
        }
        $fs->remove($files);

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    protected function doInvalidate(array $tagIds): bool
    {
        foreach ($tagIds as $tagId) {
            $tagsFolder = $this->getTagFolder($tagId);
            if (!file_exists($tagsFolder)) {
                continue;
            }

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tagsFolder, \FilesystemIterator::SKIP_DOTS)) as $itemLink) {
                if (!$itemLink->isLink()) {
                    throw new LogicException('Expected a (sym)link when iterating over tag folder, non link found: '.$itemLink);
                }

                $valueFile = $itemLink->getRealPath();
                if ($valueFile && file_exists($valueFile)) {
                    @unlink($valueFile);
                }

                @unlink((string) $itemLink);
            }
        }

        return true;
    }

    private function getFilesystem(): Filesystem
    {
        return $this->fs ?? $this->fs = new Filesystem();
    }

    private function getTagFolder(string $tagId): string
    {
        return $this->getFile($tagId, false, $this->directory.self::TAG_FOLDER.\DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR;
    }
}
