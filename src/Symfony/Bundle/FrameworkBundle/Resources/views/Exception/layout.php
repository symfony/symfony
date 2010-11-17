<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $view->getCharset() ?>"/>
        <title><?php echo $exception->getMessage() ?> (<?php echo $exception->getStatusCode() ?> <?php echo $exception->getStatusText() ?>)</title>
        <style type="text/css">
            html { background: #eee }
            body { font: 11px Verdana, Arial, sans-serif; color: #333 }
            .sf-exceptionreset, .sf-exceptionreset .block, .sf-exceptionreset #message { margin: auto }

            <?php echo $view->render('FrameworkBundle:Exception:styles.php') ?>
        </style>
        <script type="text/javascript">
            //<![CDATA[
            function toggle(id, clazz) {
                el = document.getElementById(id);
                current = el.style.display

                if (clazz) {
                    var tags = document.getElementsByTagName('*');
                    for (i = 0; i < tags.length; i++) {
                        if (tags[i].className == clazz) {
                            tags[i].style.display = 'none';
                        }
                    }
                }

                el.style.display = current == 'none' ? 'block' : 'none';
            }
            //]]>
        </script>
    </head>
    <body>
        <?php echo $view->get('slots')->get('_content') ?>
    </body>
</html>
