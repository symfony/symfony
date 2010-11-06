<h2>Request GET Parameters</h2>

<?php if (count($data->getRequestQuery()->all())): ?>
    <?php echo $view->render('WebProfilerBundle:Profiler:bag.php', array('bag' => $data->getRequestQuery())) ?>
<?php else: ?>
    <em>No GET parameters</em>
<?php endif; ?>

<h2>Request POST Parameters</h2>

<?php if (count($data->getRequestRequest()->all())): ?>
    <?php echo $view->render('WebProfilerBundle:Profiler:bag.php', array('bag' => $data->getRequestRequest())) ?>
<?php else: ?>
    <em>No POST parameters</em>
<?php endif; ?>

<h2>Request Attributes</h2>

<?php if (count($data->getRequestAttributes()->all())): ?>
    <?php echo $view->render('WebProfilerBundle:Profiler:bag.php', array('bag' => $data->getRequestAttributes())) ?>
<?php else: ?>
    <em>No attributes</em>
<?php endif; ?>

<h2>Request Cookies</h2>

<?php if (count($data->getRequestCookies()->all())): ?>
    <?php echo $view->render('WebProfilerBundle:Profiler:bag.php', array('bag' => $data->getRequestCookies())) ?>
<?php else: ?>
    <em>No cookies</em>
<?php endif; ?>

<h2>Requests Headers</h2>

<?php echo $view->render('WebProfilerBundle:Profiler:bag.php', array('bag' => $data->getRequestHeaders())) ?>

<h2>Requests Server Parameters</h2>

<?php echo $view->render('WebProfilerBundle:Profiler:bag.php', array('bag' => $data->getRequestServer())) ?>

<h2>Response Headers</h2>

<?php echo $view->render('WebProfilerBundle:Profiler:bag.php', array('bag' => $data->getResponseHeaders())) ?>

<h2>Response Session Attributes</h2>

<table>
    <tr>
        <th>Key</th>
        <th>Value</th>
    </tr>

    <?php if (count($sessionAttributes = $data->getSessionAttributes())):?>
        <?php foreach ($sessionAttributes->getRawValue() as $key => $value): ?>
            <tr>
                <th><?php echo $key ?></th>
                <td>
                    <?php if (is_object($value)): ?>
                        <em>Object</em>
                    <?php elseif (is_resource($value)): ?>
                        <em>Resource</em>
                    <?php elseif (is_array($value)): ?>
                        <em>Array</em>
                    <?php else: ?>
                        <?php echo $value ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
