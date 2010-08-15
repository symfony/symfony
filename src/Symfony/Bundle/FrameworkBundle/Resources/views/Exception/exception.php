<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $view->getCharset() ?>"/>
        <title><?php echo htmlspecialchars($manager->getMessage(), ENT_QUOTES, $view->getCharset()) ?> (<?php echo $manager->getStatusCode() ?> <?php echo $manager->getStatusText() ?>)</title>
        <style type="text/css">
            <?php echo $view->render('FrameworkBundle:Exception:styles') ?>
        </style>
        <script type="text/javascript">
        function toggle(id)
        {
            el = document.getElementById(id); el.style.display = el.style.display == 'none' ? 'block' : 'none';
        }
        </script>
    </head>
    <body>
        <center>
            <div id="message">
                <div style="float: left; margin-right: 20px">
                    <img src="data:image/png;base64,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" />
                </div>
                <div style="float: left; width: 600px">
                    <h2><?php echo $manager->getStatusCode() ?> <?php echo $manager->getStatusText() ?> - <?php echo $manager->getName() ?></h2>
                    <h1><?php echo str_replace("\n", '<br />', htmlspecialchars($manager->getMessage(), ENT_QUOTES, $view->getCharset())) ?></h1>
                </div>

                <div style="clear: both"></div>
            </div>

            <div id="main">
                <h3>
                    Logs <a href="#" onclick="toggle('logs'); return false;">...</a>
                    <?php if ($manager->countErrors()): ?>
                        <span class="error"><?php echo $manager->countErrors() ?> errors</span>
                    <?php endif; ?>
                </h3>

                <div id="logs" style="display: none">
                    <?php echo $view->render('FrameworkBundle:Exception:logs', array('logs' => $manager->getLogs())) ?>
                </div>

                <h3>Stack Trace</h3>

                <?php echo $view->render('FrameworkBundle:Exception:traces', array('traces' => $manager->getTraces())) ?>

                <h3>Content of the Output <a href="#" onclick="toggle('content'); return false;">...</a></h3>

                <div id="content" style="display: none">
                    <?php echo $manager->getCurrentContent() ?>
                </div>

                <div style="clear: both"></div>
            </div>
        </center>
    </body>
</html>
