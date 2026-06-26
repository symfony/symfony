<!-- <?= $_message = \sprintf('%s (%d %s)', $exceptionMessage, $statusCode, $statusText); ?> -->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="<?= $this->charset; ?>" />
        <meta name="robots" content="noindex,nofollow" />
        <meta name="referrer" content="no-referrer" />
        <meta name="color-scheme" content="light dark" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title><?= $_message; ?></title>
        <link rel="icon" type="image/png" href="<?= $this->include('assets/images/favicon.png.base64'); ?>" />
        <style><?= $this->include('assets/css/exception.css'); ?></style>
    </head>
    <body>
        <script>
            (function () {
                var root = document.documentElement;
                var query = matchMedia('(prefers-color-scheme: dark)');
                var systemTheme = function () { return query.matches ? 'theme-dark' : 'theme-light'; };
                var stored = function () { try { return localStorage.getItem('symfony/profiler/theme'); } catch (e) { return null; } };
                var apply = function (theme) { root.classList.remove('theme-light', 'theme-dark'); root.classList.add(theme); };

                // an explicit choice (shared with the profiler) wins; otherwise follow the OS
                apply(stored() || systemTheme());

                // keep following the OS in real time, unless the user made an explicit choice
                query.addEventListener('change', function () { if (!stored()) { apply(systemTheme()); } });
            })();
        </script>

        <?php if (class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled('symfony/http-kernel')) { ?>
            <header>
                <div class="container">
                    <div class="logo"><?= $this->include('assets/images/symfony-logo.svg'); ?> <span>Symfony Exception</span></div>

                    <div class="help-link">
                        <a href="https://symfony.com/doc/<?= preg_match('/^v?(\d+\.\d+)/', \Composer\InstalledVersions::getPrettyVersion('symfony/http-kernel') ?? \Composer\InstalledVersions::getPrettyVersion('symfony/symfony'), $m) ? $m[1] : 'current'; ?>/index.html" rel="noreferrer noopener" target="_blank">
                            <?= $this->include('assets/images/icon-book.svg'); ?> Docs
                        </a>
                    </div>
                </div>
            </header>
        <?php } ?>

        <main>
            <?= $this->include('views/exception.html.php', $context); ?>

            <?php if ($logger || $currentContent) { ?>
                <div class="container">
                    <?php if ($logger) { ?>
                        <h2>Logs<?php if ($errorCount = $logger->countErrors()) { ?> <span class="h2-badge h2-badge-error"><?= $errorCount; ?></span><?php } ?></h2>
                        <?php if ($logger->getLogs()) { ?>
                            <?= $this->include('views/logs.html.php', ['logs' => $logger->getLogs()]); ?>
                        <?php } else { ?>
                            <p class="logs-empty">No log messages.</p>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($currentContent) { ?>
                        <h2>Output content</h2>
                        <?= $currentContent; ?>
                    <?php } ?>
                </div>
            <?php } ?>

            <div class="container">
                <div aria-hidden="true" class="exc-ghost">
                    <?= $this->include('assets/images/symfony-ghost.svg.php'); ?>
                </div>
            </div>
        </main>

        <script>
            <?= $this->include('assets/js/exception.js'); ?>
        </script>
    </body>
</html>
<!-- <?= $_message; ?> -->
