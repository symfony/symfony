<!-- <?php echo $_message = sprintf('%s (%d %s)', $exceptionMessage, $statusCode, $statusText); ?> -->
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="<?php echo $this->charset; ?>" />
        <meta name="robots" content="noindex,nofollow" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title><?php echo $_message; ?></title>
        <link rel="icon" type="image/png" href="<?php echo $this->include('assets/images/favicon.png.base64'); ?>">
        <style><?php echo $this->include('assets/css/exception.css'); ?></style>
        <style><?php echo $this->include('assets/css/exception_full.css'); ?></style>
    </head>
    <body>
        <?php if (class_exists(\Symfony\Component\HttpKernel\Kernel::class)) { ?>
            <header>
                <div class="container">
                    <h1 class="logo"><?php echo $this->include('assets/images/symfony-logo.svg'); ?> Symfony Exception</h1>

                    <div class="help-link">
                        <a href="https://symfony.com/doc/<?php echo Symfony\Component\HttpKernel\Kernel::VERSION; ?>/index.html">
                            <span class="icon"><?php echo $this->include('assets/images/icon-book.svg'); ?></span>
                            <span class="hidden-xs-down">Symfony</span> Docs
                        </a>
                    </div>

                    <div class="help-link">
                        <a href="https://symfony.com/support">
                            <span class="icon"><?php echo $this->include('assets/images/icon-support.svg'); ?></span>
                            <span class="hidden-xs-down">Symfony</span> Support
                        </a>
                    </div>
                </div>
            </header>
        <?php } ?>

        <?php echo $this->include('views/exception.html.php', $context); ?>

        <script>
            <?php echo $this->include('assets/js/exception.js'); ?>
        </script>
    </body>
</html>
<!-- <?php echo $_message; ?> -->
