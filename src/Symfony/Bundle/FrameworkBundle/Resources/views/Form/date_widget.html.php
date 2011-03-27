<?php if ($widget == 'text'): ?>
    <input type="text"
        id="<?php echo $id ?>"
        name="<?php echo $name ?>"
        value="<?php echo $value ?>"
        <?php if ($disabled): ?>disabled="disabled"<?php endif ?>
        <?php if ($required): ?>required="required"<?php endif ?>
        <?php if ($class): ?>class="<?php echo $class ?>"<?php endif ?>
        <?php if (isset($attr)): echo $renderer->getTheme()->attributes($attr); endif; ?>
    />
<?php else: ?>
    <?php echo str_replace(array('{{ year }}', '{{ month }}', '{{ day }}'), array(
        $renderer['year']->getWidget(),
        $renderer['month']->getWidget(),
        $renderer['day']->getWidget(),
    ), $date_pattern) ?>
<?php endif ?>
