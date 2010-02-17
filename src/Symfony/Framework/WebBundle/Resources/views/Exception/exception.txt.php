[exception]   <?php echo $code.' | '.$text.' | '.$name ?>
[message]     <?php echo $message ?>
<?php if (isset($traces) && count($traces) > 0): ?>
[stack trace]
<?php foreach ($traces as $line): ?>
  <?php echo $line ?>

<?php endforeach; ?>
<?php endif; ?>
[symfony]     v. <?php echo SYMFONY_VERSION ?> (symfony-project.org)
[PHP]         v. <?php echo PHP_VERSION ?>
