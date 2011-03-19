<div>
    <?php echo $renderer->getErrors() ?>
    <?php foreach ($fields as $field): ?>
        <?php echo $field->getRow(); ?>
    <?php endforeach; ?>
    <?php echo $renderer->getRest() ?>
</div>

