<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Resources;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\Profiler\CodeExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\DataCollector\RateLimiterDataCollector;
use Symfony\Component\RateLimiter\Debug\TraceableRateLimiterBuilder;
use Symfony\Component\RateLimiter\Debug\TraceableRateLimiterFactory;
use Symfony\Component\RateLimiter\Exception\MaxWaitDurationExceededException;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class RateLimiterPanelTest extends TestCase
{
    public function testSavedProfileIsRendered()
    {
        if (!class_exists(RateLimiterDataCollector::class)) {
            $this->markTestSkipped('The RateLimiter component is not installed.');
        }

        $requests = new RequestStack();
        $request = Request::create('/');
        $requests->push($request);
        $collector = new RateLimiterDataCollector($requests);
        $builder = new TraceableRateLimiterBuilder(new RateLimiterBuilder(new InMemoryStorage()), $collector);
        $api = $builder->fixedWindow('api', 2, '1 minute');
        $limiter = $api->create('user-42');
        $limiter->consume();
        $limiter->consume(2);
        $bucket = $builder->tokenBucket('reservation', 2, '1 minute')->create('user-42');
        $bucket->reserve(2);
        $bucket->reserve();
        $builder->noop()->create('user-42')->consume();
        $limiter->reset();
        $compound = $builder->compound($builder->fixedWindow('short', 1, '1 minute'), $builder->slidingWindow('long', 5, '1 hour'))->create('user-42');
        $compound->consume();
        $compound->consume();
        $line = __LINE__ + 1;
        $api->create()->consume();
        $waiting = $builder->tokenBucket('waiting', 1, '1 hour')->create('user-42');
        $waiting->reserve();
        try {
            $waiting->reserve(1, 1);
        } catch (MaxWaitDurationExceededException) {
        }
        try {
            $builder->fixedWindow('failing', 1, '1 minute')->create('user-42')->consume(2);
        } catch (\InvalidArgumentException) {
        }
        (new TraceableRateLimiterFactory(new RateLimiterFactory(['id' => 'own', 'policy' => 'no_limit'], new InMemoryStorage()), 'own', $collector))->create()->consume();
        $collector->collect($request, new Response());
        $collector = unserialize(serialize($collector));

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2).'/Resources/views', 'WebProfiler');
        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new CodeExtension('', \dirname(__DIR__, 2), 'UTF-8'));
        $template = $twig->load('@WebProfiler/Collector/rate_limiter.html.twig');
        $panel = $template->renderBlock('panel', ['collector' => $collector]);
        $menu = $template->renderBlock('menu', ['collector' => $collector]);

        $this->assertMatchesRegularExpression('/Rate Limiter <small>9 limiters<\/small>/', $panel);
        $this->assertMatchesRegularExpression('/<span class="value">13<\/span>\s*<span class="label">Calls/', $panel);
        $this->assertMatchesRegularExpression('/<span class="value rate-limiter-warning">3<\/span>\s*<span class="label">Rejected/', $panel);
        $this->assertMatchesRegularExpression('/<span class="value rate-limiter-error">1<\/span>\s*<span class="label">Failed/', $panel);
        $this->assertStringContainsString('label-status-error', $menu);

        $timeline = $this->extract($panel, '/<table class="rate-limiter-table" id="rate-limiter-timeline">.*?<\/table>/s');
        $this->assertStringContainsString('<dt>From</dt><dd class="rate-limiter-mono"><abbr title="'.__FILE__.'">Tests/Resources/RateLimiterPanelTest.php:'.$line.'</abbr></dd>', $timeline);
        $this->assertMatchesRegularExpression('/aria-expanded="false".*?<tr data-row-of="\d+" class="rate-limiter-details\s*" hidden>\s*<td colspan="6"><dl class="rate-limiter-facts"><div><dt>From<\/dt><dd class="rate-limiter-mono"><abbr title="[^"]+">Tests\/Resources\/RateLimiterPanelTest\.php:'.$line.'</s', $timeline, 'Collapsed details are hidden when rendered, not by JavaScript.');
        $this->assertMatchesRegularExpression('/<tr data-row-of="\d+" class="rate-limiter-details rate-limiter-failed">/', $timeline, 'Expanded details are visible when rendered.');
        $this->assertMatchesRegularExpression('/data-toggle="\d+" aria-expanded="true".*?<strong>waiting<\/strong>.*?<span class="label status-warning">rejected<\/span>.*?>0\/1<\/span>.*?\d\d:\d\d:\d\d <span class="text-muted">\(retry\)<\/span>.*?<dt>Exception<\/dt><dd class="rate-limiter-mono text-danger"><abbr title="Symfony\\\\Component\\\\RateLimiter\\\\Exception\\\\MaxWaitDurationExceededException">MaxWaitDurationExceededException<\/abbr>: The rate limiter wait time/s', $timeline, 'A reservation that cannot wait long enough is a rejection, with its tokens, retry time and exception.');
        $this->assertMatchesRegularExpression('/data-toggle="\d+" aria-expanded="true".*?<strong>failing<\/strong>.*?<span class="label status-error">failed<\/span>.*?<dt>Exception<\/dt><dd class="rate-limiter-mono text-danger"><abbr title="InvalidArgumentException">InvalidArgumentException<\/abbr>/s', $timeline, 'A failed call is expanded and shows its exception.');
        $this->assertMatchesRegularExpression('/<strong>api<\/strong>\s*<span class="rate-limiter-key rate-limiter-mono" title="user-42">user-42<\/span>.*?consume\(1\)<\/code>.*?status-success">accepted<\/span>.*?>1\/2<\/span>/s', $timeline);
        $this->assertMatchesRegularExpression('/consume\(2\)<\/code>.*?<span class="label status-warning">rejected<\/span>.*?text-danger.*?\d\d:\d\d:\d\d <span class="text-muted">\(retry\)<\/span>/s', $timeline);
        $this->assertStringContainsString('<span class="label">reset</span>', $timeline);
        $this->assertMatchesRegularExpression('/reserve\(2\)<\/code>.*?"label">reserved<\/span>.*?<td>\d\d:\d\d:\d\d<\/td>/s', $timeline);
        $this->assertMatchesRegularExpression('/reserve\(1\)<\/code>.*?"label">reserved<\/span>.*?<td>\d\d:\d\d:\d\d<\/td>/s', $timeline);
        $this->assertMatchesRegularExpression('/status-success">accepted<\/span><\/td>\s*<td><span class="rate-limiter-tokens rate-limiter-mono">∞<\/span>/', $timeline);
        $this->assertMatchesRegularExpression('/data-toggle="\d+" aria-expanded="true".*?└<\/span> <strong>short<\/strong>/s', $timeline, 'A rejected compound call is expanded to show the calls on its limiters.');
        foreach (['Reason', 'Decided by', 'Resets at', 'Retry at', 'Acts at'] as $fact) {
            $this->assertStringNotContainsString('<dt>'.$fact.'</dt>', $timeline);
        }
        $this->assertStringNotContainsString('>ms</th>', $timeline);

        $section = fn (string $name) => $this->extract($panel, '/<div class="rate-limiter-section" id="rate-limiter-limiter-\d+">\s*<h3>\s*(?:<span class="badge">[^<]+<\/span>\s*)?<span>'.preg_quote($name, '/').'<\/span>.*?<\/table>/s');

        $api = $section('api');
        $this->assertMatchesRegularExpression('/<h3>\s*<span>own<\/span>\s*<\/h3>/', $panel, 'A limiter whose policy is unknown gets no badge.');
        $this->assertMatchesRegularExpression('/<span class="badge">fixed window<\/span>\s*<span>api<\/span>\s*<\/h3>/', $api, 'The policy badge comes before the name.');
        $this->assertSame(1, substr_count($api, '<table'), 'Each limiter has one table.');
        $this->assertMatchesRegularExpression('/<tbody>\s*<tr>\s*<th colspan="4" class="font-normal">user-42<\/th>\s*<\/tr>.*?<\/tbody>\s*<tbody>\s*<tr>\s*<th colspan="4" class="font-normal">no key<\/th>/s', $api, 'Each key is a full-width row heading its calls, like events in the event panel.');
        $this->assertStringContainsString('>1/2</span>', $api);
        $this->assertMatchesRegularExpression('/<tr class="rate-limiter-rejected">\s*<td><code>consume\(2\)<\/code>/', $api, 'Rejected rows get the warning stripe of the logger panel.');
        $this->assertMatchesRegularExpression('/<tr class="rate-limiter-failed">.*?<strong>failing<\/strong>/s', $timeline, 'Failed rows get the error stripe.');
        $this->assertMatchesRegularExpression('/consume\(2\)<\/code>.*?status-warning">rejected<\/span>.*?<td>\d\d:\d\d:\d\d <span class="text-muted">\(retry\)<\/span><\/td>\s*<\/tr>/s', $api, 'Limiter rows show when the call can be retried, like the timeline details.');
        $this->assertMatchesRegularExpression('/<th class="col-at">Frees up at<\/th>.*?consume\(1\)<\/code>.*?status-success">accepted<\/span>.*?<td>\d\d:\d\d:\d\d<\/td>/s', $api);
        $reservation = $section('reservation');
        $this->assertStringContainsString('<span class="badge">token bucket</span>', $reservation);
        $this->assertMatchesRegularExpression('/<th colspan="4" class="font-normal">user-42<\/th>/', $reservation, 'A limiter with a single key still names it.');

        $this->assertStringContainsString('<strong>compound</strong>', $timeline, 'The timeline names builder compounds "compound", their limiters are listed below.');
        $this->assertStringNotContainsString('compound(short, long)', $timeline);

        $compound = $section('compound(short, long)');
        $this->assertMatchesRegularExpression('/status-success">accepted<\/span><\/td>\s*<td><div class="rate-limiter-chips"><a href="#rate-limiter-limiter-\d+" class="label status-success">short 0\/1<\/a><a href="#rate-limiter-limiter-\d+" class="label status-success">long 4\/5<\/a><\/div><\/td>/', $compound, 'Each call shows the tokens left on each limiter of the compound.');
        $this->assertMatchesRegularExpression('/status-warning">rejected<\/span><\/td>\s*<td><div class="rate-limiter-chips"><a href="#rate-limiter-limiter-\d+" class="label status-warning">short 0\/1<\/a><a href="#rate-limiter-limiter-\d+" class="label disabled" title="Not called">long —<\/a><\/div>/', $compound, 'The rejecting limiter is flagged, and the ones the compound did not reach are shown as skipped.');

        preg_match('/<a href="#(rate-limiter-limiter-\d+)" class="label status-success">short /', $compound, $link);
        $short = $section('short');
        $this->assertStringStartsWith('<div class="rate-limiter-section" id="'.$link[1].'">', $short, 'Each limiter of a compound links to its own table.');
        $this->assertMatchesRegularExpression('/<span>compound\(short, long\)<\/span>.*?<span>short<\/span>.*?<span>long<\/span>/s', $panel, 'The limiters of a compound follow it.');
        $this->assertStringNotContainsString('>#</th>', $short, 'Limiter tables have no call number column.');
    }

    private function extract(string $panel, string $pattern): string
    {
        $this->assertMatchesRegularExpression($pattern, $panel);
        preg_match($pattern, $panel, $match);

        return $match[0];
    }
}
