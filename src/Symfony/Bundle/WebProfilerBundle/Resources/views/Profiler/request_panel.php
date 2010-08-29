<h2>Request GET Parameters</h2>

<?php if (count($data->getRequestQuery()->all())): ?>
    <?php echo $view->render('WebProfilerBundle:Profiler:bag', array('bag' => $data->getRequestQuery())) ?>
<?php else: ?>
    <em>No GET parameters</em>
<?php endif; ?>

<h2>Request POST Parameters</h2>

<?php if (count($data->getRequestRequest()->all())): ?>
    <?php echo $view->render('WebProfilerBundle:Profiler:bag', array('bag' => $data->getRequestRequest())) ?>
<?php else: ?>
    <em>No POST parameters</em>
<?php endif; ?>

<h2>Request Cookies</h2>

<?php if (count($data->getRequestCookies()->all())): ?>
    <?php echo $view->render('WebProfilerBundle:Profiler:bag', array('bag' => $data->getRequestCookies())) ?>
<?php else: ?>
    <em>No cookies</em>
<?php endif; ?>

<h2>Requests Headers</h2>

<?php echo $view->render('WebProfilerBundle:Profiler:bag', array('bag' => $data->getRequestHeaders())) ?>

<h2>Requests Server Parameters</h2>

<?php echo $view->render('WebProfilerBundle:Profiler:bag', array('bag' => $data->getRequestServer())) ?>

<h2>Response Headers</h2>

<?php echo $view->render('WebProfilerBundle:Profiler:bag', array('bag' => $data->getResponseHeaders())) ?>

<h2>Response Session Attributes</h2>

<?php //echo $view->render('WebProfilerBundle:Profiler:bag', array('bag' => $data->getSessionAttributes())) ?>
