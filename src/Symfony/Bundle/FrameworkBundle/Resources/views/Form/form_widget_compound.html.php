<div <?php echo $view['form']->renderBlock('widget_container_attributes') ?>>
    <?php if (!$form->hasParent() && $errors): ?>
    <tr>
        <td colspan="2">
            <?php echo $view['form']->errors($form) ?>
        </td>
    </tr>
    <?php endif ?>
    <?php echo $view['form']->renderBlock('form_rows') ?>
    <?php echo $view['form']->rest($form) ?>
</div>
