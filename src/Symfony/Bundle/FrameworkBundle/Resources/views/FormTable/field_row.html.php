<tr>
    <td>
        <?php echo $view['form']->label($form, isset($label) ? $label : null) ?>
    </td>
    <td>
        <?php echo $view['form']->errors($form) ?>
        <?php echo $view['form']->widget($form) ?>
    </td>
</tr>
