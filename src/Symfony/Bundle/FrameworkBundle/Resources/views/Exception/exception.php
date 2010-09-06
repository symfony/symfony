<?php if (!$embedded): ?>
    <?php $view->extend('FrameworkBundle:Exception:layout') ?>
<?php endif; ?>

<div class="sf-exceptionreset">
    <div id="message">
        <div style="float: left; margin-right: 20px">
            <img alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABHpJREFUeNrsV1tIY1cUzUvzMJOJk/isjkYnOlLBVE1spjQElXb6U/yyklGL1hedD7FFjDBQbBGrfohfgyhSKgYKLfhX2qotUzrWDnUCo7VgjTA2iI4ZNY+bh1G7Tri3pGn05tKB+egcWJzXPvusu8/e+5zLPzs74z3PIuA95/KCAI/4QCxiym3ge6CDg9oOes1ttr1EbJpmZmaKhUKheXFxsWBqaqoWQxabzRaOJ2uxWIg+W1tbW1V1dfXVk5OTR01NTRfqZyXA5/Otp6enlNls7isvL8/o7u6eQP+9eLJJSUkT4+PjbysUCjFkhrH2Yzb9rATI5qiswGdyuXx9cHCwvrGx8S76l4GrtNhj4GhkZKQeMmTzEvR/T+SsWAnAjEyTKDSmpaUt6fX6KXxlclZWlpRM7Ozs+N1udwhzcsgbE92cKwEezvb68PAw1dnZWRZPdm9vj+rr67sOX/n5mYUhIUCAzW80NDR8pFQqZcxYLMgckSGyzNh/scDrADHp15G46ugw6nS6fDalJpMpH8djhNx9eugtwAv8GNfJ490F8N5ko9G4IpVKRQi/eQx9V1FRccdgMHhLSkrMFxFYX1//YXl5+crKysoHhA/C8R2/3x9eWloqx16hhAiMjY1JUPnpc32ysbHxS1VV1c21tbXd+fn5P0tLSwOwRopEIhETmUAgELTb7b7V1VVJbW1tTllZWTbkviosLKzUaDR5tFppT09PIFEL8EZHRx+iqfN6va79/f2j3NzcfCSkiM+g7wUpF0VRkYQkk8lEWq1WpVar5YyOLRT0L6Go0bX39va+Enev8wiQMjQ0dAtVvcvlMqlUKiWXFI81HqxZQPPL/v7+WSYVJ0pAQz4MIF+YbLVaH6SkpIi5EAiHw6cDAwNash4QAhT22kooCnJycr4Qi8VCePIZztmDFJt8fHwcmfN4PFuHh4cbCDktzKu5KMSLi4u7Q6HQawKBgB8MBkn4GBIi0NzcrGfacDCf0+l0ZGZmFpL+9va2GwrDIOIuKio6d3dsHEIkmbKzs3Wc8wDM50T1UkRAJLLjSPIxFplDjGt2d3dVGRkZCmbsHB1Uenr6b6gZAk4ueeAfCamlpWXuCkqsHMzqRiT8gUjZwSWUhWi4hqNTkLnNzU3H3NxcM5o/Rb8HuKRictPdrays/BBKJeRrY4ELyHFwcPAY/nFMatJn5nBkauSKO0RH1K2ZsAVEMN+9mpqal+GIFCLAj6/7l8M5HI77cNA9pg8e6QUFBTdoZ/0VRK5BP39hYWENCc2EdjghH+jq6hJA2AAiQnKW8IPP4VPvIxGlRcvBMnk+ny8MEkfY/DKI5tGX0BMQnoWOT0lar6ure5VEAicfaG9vn0DzXaCVPLOAN4BvouWQ44MwvQemp+CcstTU1Eu4P0i+eBP4FmgkrzryWJmcnFRyzoSktLa2Rj8qZzFnYXnk2iBzi+lPT0/zLnJCTg8S2iIku+nPEX9Ayzy7/wK876IRBgzAJ8DTqPGn9JiBlvl7Deuj98W/4f+ewF8CDADfMn9DHK75mAAAAABJRU5ErkJggg==" />
        </div>
        <div style="float: left; width: 600px">
            <h1><?php echo $view->get('code')->formatFileFromText(str_replace("\n", '<br />', htmlspecialchars($exception->getMessage(), ENT_QUOTES, $view->getCharset()))) ?></h1>
            <h2><strong><?php echo $exception->getStatusCode() ?></strong> <?php echo $exception->getStatusText() ?> - <?php echo $exception->getClass() ?></h2>

            <?php if ($previousCount = count($exception->getPreviouses())): ?>
                <div class="linked"><span><strong><?php echo $previousCount ?></strong> linked Exception<?php if ($previousCount > 1): ?>s<?php endif; ?>:</span>
                    <ul>
                        <?php foreach ($exception->getPreviouses() as $i => $previous): ?>
                            <li>
                                <?php echo $view->get('code')->abbrClass($previous->getClass()) ?> <a href="#traces_link_<?php echo $i + 1 ?>" onclick="toggle('traces_<?php echo $i + 1 ?>', 'traces');">&raquo;</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <div style="clear: both"></div>
    </div>

    <?php echo $view->render('FrameworkBundle:Exception:traces', array('exception' => $exception, 'position' => 0, 'count' => $previousCount)) ?>

    <?php foreach ($exception->getPreviouses() as $i => $previous): ?>
        <?php echo $view->render('FrameworkBundle:Exception:traces', array('exception' => $previous, 'position' => $i + 1, 'count' => $previousCount)) ?>
    <?php endforeach; ?>

    <?php if (null !== $logger): ?>
        <div class="block">
            <h3>
                <?php if ($logger->countErrors()): ?>
                    <span class="error"><?php echo $logger->countErrors() ?> error<?php if ($logger->countErrors() > 1): ?>s<?php endif; ?></span>
                <?php endif; ?>
                Logs <a href="#" onclick="toggle('logs'); return false;">&raquo;</a>
            </h3>

            <div id="logs" style="display: none">
                <?php echo $view->render('FrameworkBundle:Exception:logs', array('logs' => $logger->getLogs())) ?>
            </div>

        </div>
    <?php endif; ?>

    <?php if ($currentContent): ?>
        <div class="block">
            <h3>Content of the Output <a href="#" onclick="toggle('content'); return false;">&raquo;</a></h3>

            <div id="content" style="display: none">
                <?php echo $currentContent ?>
            </div>

            <div style="clear: both"></div>
        </div>
    <?php endif; ?>
</div>
