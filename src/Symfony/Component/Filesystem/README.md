Filesystem Component
====================

Filesystem provides basic utility to manipulate the file system:

    use Symfony\Component\Filesystem\Filesystem;

    $filesystem = new Filesystem();

    $filesystem->copy($originFile, $targetFile, $override = false);

    $filesystem->mkdir($dirs, $mode = 0777);

    $filesystem->touch($files);

    $filesystem->remove($files);

    $filesystem->chmod($files, $mode, $umask = 0000);

    $filesystem->rename($origin, $target);

    $filesystem->symlink($originDir, $targetDir, $copyOnWindows = false);

    $filesystem->makePathRelative($endPath, $startPath);

    $filesystem->mirror($originDir, $targetDir, \Traversable $iterator = null, $options = array());

    $filesystem->isAbsolutePath($file);

Resources
---------

You can run the unit tests with the following command:

    phpunit
