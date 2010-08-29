<?php echo sprintf('<?xml version="1.0" encoding="%s" ?>', $view->getCharset())."\n" ?>
<error code="<?php echo $exception->getStatusCode() ?>" message="<?php echo $exception->getStatusText() ?>">
    <exception class="<?php echo $exception->getClass() ?>" message="<?php echo $exception->getMessage() ?>">
        <?php echo $view->render('FrameworkBundle:Exception:traces', array('exception' => $exception, 'position' => 0, 'count' => ($previousCount = count($exception->getPreviouses())))) ?>
    </exception>
<?php if ($previousCount): ?>
<?php foreach ($exception->getPreviouses() as $i => $previous): ?>
    <exception class="<?php echo $previous->getClass() ?>" message="<?php echo $previous->getMessage() ?>">
        <?php echo $view->render('FrameworkBundle:Exception:traces', array('exception' => $previous, 'position' => $i + 1, 'count' => $previousCount)) ?>
    </exception>
<?php endforeach; ?>
<?php endif; ?>
</error>
