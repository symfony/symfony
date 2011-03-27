<div>
    <?php echo $renderer->getErrors() ?>
    <?php foreach ($renderer as $field): ?>
        <?php echo $field->getRow(); ?>
    <?php endforeach; ?>
    <?php echo $renderer->getRest() ?>
</div>

