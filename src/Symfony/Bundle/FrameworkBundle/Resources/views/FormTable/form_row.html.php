<tr>
    <td>
        <?php echo $view['form']->label($form, isset($label) ? $label : null) ?>
    </td>
    <td>
        <?php if (!$compound): ?>
            <?php echo $view['form']->errors($form) ?>
        <?php endif ?>
        <?php echo $view['form']->widget($form) ?>
    </td>
</tr>
