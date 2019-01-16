<div <?php echo $view['form']->block($form, 'widget_container_attributes') ?>>
<?php foreach ($form as $child): ?>
    <?php echo $view['form']->widget($child) ?>
    <?php echo $view['form']->label($child, null, ['translation_domain' => $choice_translation_domain]) ?>
<?php endforeach ?>
</div>
