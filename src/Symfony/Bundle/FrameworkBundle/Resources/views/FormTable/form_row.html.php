<tr>
    <td>
        <?php echo $view['form']->label($form); ?>
    </td>
    <td>
        <?php echo $view['form']->errors($form); ?>
        <?php echo $view['form']->widget($form); ?>
        <?php echo $view['form']->help($form); ?>
    </td>
</tr>
