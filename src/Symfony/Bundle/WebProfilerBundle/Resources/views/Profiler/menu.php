<ul>
    <?php foreach ($templates as $name => $template): ?>
        <li
            <?php if ($name == $panel): ?>class="selected"<?php endif; ?>
        >
            <a href="<?php echo $view->get('router')->generate('_profiler_panel', array('token' => $token, 'panel' => $name)) ?>">
                <?php echo $view->render($template, array('data' => $profiler->getCollector($name))) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php echo $view->get('actions')->render('WebProfilerBundle:Profiler:menu', array('token' => $token)) ?>

<?php echo $view->render('WebProfilerBundle:Profiler:admin', array('token' => $token)) ?>
