<div <?php echo $view['form']->block($form, 'widget_container_attributes') ?>>
<?php foreach ($form as $child): ?>
    <?php echo $view['form']->widget($child) ?>
    <?php echo $view['form']->label($child) ?>
<?php endforeach ?>
</div>
