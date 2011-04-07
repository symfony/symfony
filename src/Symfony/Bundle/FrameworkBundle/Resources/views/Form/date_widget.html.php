<?php if ($widget == 'text'): ?>
    <input type="text"
        id="<?php echo $id ?>"
        name="<?php echo $name ?>"
        value="<?php echo $value ?>"
        <?php if ($read_only): ?>disabled="disabled"<?php endif ?>
        <?php if ($required): ?>required="required"<?php endif ?>
        <?php if ($class): ?>class="<?php echo $class ?>"<?php endif ?>
        <?php if (isset($attr)): echo $form['view']->attributes($attr); endif; ?>
    />
<?php else: ?>
    <?php echo str_replace(array('{{ year }}', '{{ month }}', '{{ day }}'), array(
        $form['view']->widget($context['year']),
        $form['view']->widget($context['month']),
        $form['view']->widget($context['day']),
    ), $date_pattern) ?>
<?php endif ?>
