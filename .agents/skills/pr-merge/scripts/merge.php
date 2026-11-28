#!/usr/bin/env php
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Merge pull requests the way the Symfony core team does (see ../SKILL.md).
 *
 * "prepare" works locally only: it creates a detached worktree at
 * <remote>/<branch>, adds one merge commit per pull request in the given
 * order, attaches their comments as git notes, and prints the summary to
 * confirm before anything is pushed. "push" then does every outward step.
 * The user's checkout and branches are never touched.
 *
 * Usage:
 *   merge.php prepare <branch> <N>[:<category>]... [options]
 *   merge.php continue <worktree>
 *   merge.php status <worktree>
 *   merge.php push <worktree> [--no-thanks] [--no-close-issues] [--no-docs-issue]
 *   merge.php abort <worktree>
 *
 * Options of prepare:
 *   --remote=<remote>    Git remote (or URL) of the repository (default: origin)
 *   --repo=<owner/name>  GitHub repository (default: read from the remote URL)
 *   --worktree=<dir>     Where to create the worktree (default: a new temporary directory)
 *   --squash             Squash every PR that has several commits, even from several authors
 *   --no-squash          Squash nothing
 *   --retarget           Accept PRs opened for another branch, and rebase them onto <branch>
 *   --security=<id>      Security merge of a single PR (CVE-2025-12345 or GHSA-xxxx-xxxx-xxxx)
 *   --edit=<N>           Stop before merging #N, with its head checked out to amend it
 *
 * continue resumes after a conflict or --edit, status reads the gates again
 * and prints the summary, abort removes the worktree and everything prepare
 * created.
 *
 * Exit codes: 0 success, 1 failure or hard stop, 2 bad arguments,
 * 3 merge or rebase conflict (resolve it, then run continue),
 * 4 stopped by --edit (amend, then run continue),
 * 5 the remote branch moved since prepare (run prepare again).
 */

if ('cli' !== \PHP_SAPI) {
    throw new Exception('This script must be run from the command line.');
}

error_reporting(\E_ALL);

set_error_handler(static function (int $type, string $msg, string $file, int $line): void {
    throw new ErrorException($msg, 0, $type, $file, $line);
});

const EXIT_FAILURE = 1;
const EXIT_USAGE = 2;
const EXIT_CONFLICT = 3;
const EXIT_EDIT = 4;
const EXIT_MOVED = 5;

const CATEGORIES = ['feature', 'bug', 'minor', 'security', 'tidy'];
const DOCUMENTATION_REPOS = ['ux', 'ai', 'twig', 'reprise'];
const BOTS = ['carsonbot', 'travisbot', 'ubot-io'];
const GITHUB_SYNC_TRIES = 30;
const GITHUB_SYNC_DELAY = 10;

function stop(string $message, int $code = EXIT_FAILURE): never
{
    throw new RuntimeException($message, $code);
}

/**
 * Titles, names, branches and commit subjects come from contributors: their control characters must
 * not rewrite the terminal, nor hide or reorder lines of the summary.
 */
function sanitize(string $text): string
{
    return preg_replace_callback('/[\x00-\x08\x0B-\x1F\x7F]|\xC2[\x80-\x9F]|\xD8\x9C|\xE2\x80[\x8E\x8F\xA8-\xAE]|\xE2\x81[\xA6-\xA9]/', static fn (array $m): string => '\x'.bin2hex($m[0]), $text) ?? $text;
}

/**
 * The merge commit archives text written by contributors: it keeps no control characters but new lines and tabs.
 */
function controlFree(string $text): string
{
    return preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $text) ?? $text;
}

function out(string $line = ''): void
{
    echo sanitize($line), "\n";
}

function usage(): never
{
    stop(<<<'EOTXT'
        Usage:
          merge.php prepare <branch> <N>[:<category>]... [--remote=<remote>] [--repo=<owner/name>] [--worktree=<dir>]
                    [--squash|--no-squash] [--retarget] [--security=<id>] [--edit=<N>]
          merge.php continue <worktree>
          merge.php status <worktree>
          merge.php push <worktree> [--no-thanks] [--no-close-issues] [--no-docs-issue]
          merge.php abort <worktree>
        EOTXT, EXIT_USAGE);
}

/**
 * @return array{0: list<string>, 1: array<string, string|true>}
 */
function parseArgs(array $args, array $allowed): array
{
    $positional = [];
    $options = [];

    foreach ($args as $arg) {
        if (!str_starts_with($arg, '--')) {
            $positional[] = $arg;
            continue;
        }

        [$name, $value] = explode('=', substr($arg, 2), 2) + [1 => true];

        if (!in_array($name, $allowed, true)) {
            stop('Unknown option: '.$arg, EXIT_USAGE);
        }

        $options[$name] = $value;
    }

    return [$positional, $options];
}

/**
 * @return array{0: int, 1: string, 2: string}
 */
function run(array $command, ?string $cwd = null, ?string $input = null, array $env = []): array
{
    $stdout = tmpfile();
    $stderr = tmpfile();
    $process = proc_open($command, [['pipe', 'r'], $stdout, $stderr], $pipes, $cwd, $env ? $env + getenv() : null);

    if (null !== $input) {
        fwrite($pipes[0], $input);
    }
    fclose($pipes[0]);
    $status = proc_close($process);

    rewind($stdout);
    rewind($stderr);

    return [$status, stream_get_contents($stdout), stream_get_contents($stderr)];
}

/**
 * @return array{0: int, 1: string, 2: string}
 */
function gitRun(array $args, ?string $cwd = null, ?string $input = null, array $env = []): array
{
    return run(['git', ...gitOptions(), ...$args], $cwd, $input, $env);
}

/**
 * The hooks of the repository and rerere stay out of the merge. So do the filters configured on this
 * machine, git-lfs's for example, since the .gitattributes of a PR can apply them to any file.
 */
function gitOptions(): array
{
    static $options;

    if (null === $options) {
        $options = ['-c', 'core.hooksPath=/dev/null', '-c', 'rerere.enabled=false', '-c', 'color.ui=false'];
        $keys = array_filter(explode("\n", run(['git', 'config', '--name-only', '--get-regexp', '^filter\.'])[1]));

        foreach (array_unique(array_map(static fn (string $key): string => substr($key, 7, strrpos($key, '.') - 7), $keys)) as $driver) {
            array_push($options, '-c', "filter.$driver.clean=", '-c', "filter.$driver.smudge=", '-c', "filter.$driver.process=", '-c', "filter.$driver.required=false");
        }
    }

    return $options;
}

function git(array $args, ?string $cwd = null, ?string $input = null, array $env = []): string
{
    [$status, $stdout, $stderr] = gitRun($args, $cwd, $input, $env);

    if (0 !== $status) {
        stop(sprintf("git %s failed:\n%s", implode(' ', $args), trim($stdout."\n".$stderr)));
    }

    return $stdout;
}

function isAncestor(string $ancestor, string $commit, ?string $cwd): bool
{
    return 0 === gitRun(['merge-base', '--is-ancestor', $ancestor, $commit], $cwd)[0];
}

function lsRemote(string $remote, string $ref, ?string $cwd): ?string
{
    $line = trim(git(['ls-remote', $remote, $ref], $cwd));

    return '' !== $line ? strtok($line, "\t") : null;
}

function gh(array $args, ?string $input = null): string
{
    [$status, $stdout, $stderr] = run(['gh', ...$args], null, $input);

    if (0 !== $status) {
        stop(sprintf("gh %s failed:\n%s", implode(' ', $args), trim($stderr ?: $stdout)));
    }

    return $stdout;
}

function api(string $path): array
{
    return json_decode(gh(['api', $path]), true, 512, \JSON_THROW_ON_ERROR);
}

/**
 * @return list<array> The pages of a paginated endpoint
 */
function apiPages(string $path): array
{
    return json_decode(gh(['api', '--paginate', '--slurp', $path]), true, 512, \JSON_THROW_ON_ERROR);
}

function apiList(string $path): array
{
    return array_merge(...apiPages($path));
}

function apiWrite(string $method, string $path, array $payload): array
{
    return json_decode(gh(['api', '-X', $method, $path, '--input', '-'], json_encode($payload, \JSON_THROW_ON_ERROR)), true, 512, \JSON_THROW_ON_ERROR);
}

function graphql(string $query, array $variables): array
{
    $result = json_decode(gh(['api', 'graphql', '--input', '-'], json_encode(['query' => $query, 'variables' => $variables], \JSON_THROW_ON_ERROR)), true, 512, \JSON_THROW_ON_ERROR);

    return $result['data'] ?? [];
}

function releaseInfo(string $version): ?array
{
    static $releases = [];

    if (!array_key_exists($version, $releases)) {
        try {
            $context = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'symfony-pr-merge']]);
            $releases[$version] = json_decode(file_get_contents('https://symfony.com/releases/'.rawurlencode($version).'.json', false, $context), true, 512, \JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $releases[$version] = null;
        }
    }

    return $releases[$version];
}

function stateFile(string $worktree): string
{
    if (!is_dir($worktree)) {
        stop('No such worktree: '.$worktree, EXIT_USAGE);
    }

    return trim(git(['rev-parse', '--absolute-git-dir'], $worktree)).'/pr-merge.json';
}

function loadState(string $worktree): array
{
    $file = stateFile($worktree);

    if (!is_file($file)) {
        stop($worktree.' was not created by "merge.php prepare".', EXIT_USAGE);
    }

    return json_decode(file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);
}

function saveState(array $state): void
{
    file_put_contents($state['state_file'], json_encode($state, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
}

function script(): string
{
    return 'php '.__FILE__;
}

/**
 * @return array{0: string, 1: string}|null
 */
function githubRepository(string $url): ?array
{
    if (!preg_match('#^(?:git@github\.com:|https?\://(?:[a-zA-Z0-9-_]+@)?github\.com/|git\://github\.com/)(.+?)/(.+?)(?:\.git)?/?\s*$#', $url, $match)) {
        return null;
    }

    return [$match[1], $match[2]];
}

function advisoryId(string $id): string
{
    $id = trim(ltrim(strtolower($id), ' #'));

    if (preg_match('/^(?:cve-)?(\d{4}-\d{4,})$/', $id, $m)) {
        return 'cve-'.$m[1];
    }

    if (preg_match('/^ghsa(?:-[23456789cfghjmpqrvwx]{4}){3}$/', $id)) {
        return $id;
    }

    stop('Invalid advisory id. Should be a CVE number like CVE-2025-12345 or a GHSA id like GHSA-xxxx-xxxx-xxxx.', EXIT_USAGE);
}

/**
 * The name of the branch in the merge message: its version when it isn't one already.
 */
function niceBranch(string $branch, string $base, ?string $cwd): string
{
    if (preg_match('/^\d+\.\d+(?:\.x)?$/', $branch)) {
        return $branch;
    }

    [$status, $composer] = gitRun(['show', $base.':composer.json'], $cwd);
    $composer = 0 === $status ? json_decode($composer, true) : null;

    return $composer['extra']['branch-alias']['dev-'.$branch] ?? $branch;
}

function tableRow(string $body, string $question): ?string
{
    if (!preg_match('/^\|?[ \t]*'.preg_quote($question, '/').'[ \t]*\|(.*)$/mi', $body, $m)) {
        return null;
    }

    return trim(preg_replace('/<!--.*?(?:-->|$)/', '', $m[1]), " \t|");
}

/**
 * Extracts the numbers of the issues that the closing keywords of a PR body point to.
 *
 * @return list<int>
 */
function closingReferences(string $body, string $owner, string $project): array
{
    // GitHub ignores the keywords found in code blocks and in quoted text
    $body = preg_replace(['{^```.*?^```}ms', '{`[^`]*`}', '{^\s*>.*$}m'], '', $body) ?? $body;

    $reference = sprintf(
        '(?:%s/issues/|(?:%s)?\#)(\d+)(?!\d)',
        preg_quote('https://github.com/'.$owner.'/'.$project, '{'),
        preg_quote($owner.'/'.$project, '{')
    );
    $keyword = '(?<![\w-])(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)[\s:]+';

    // unlike GitHub, which links the first reference only, all the ones a keyword is followed by are
    // collected, as in "Fixes #12 #34, #56"; they have to sit on the line of the keyword, so that a
    // reference opening the next paragraph is not swallowed
    $separator = '(?:[ \t]*[,&+][ \t]*|[ \t]+and[ \t]+|[ \t]+)';

    if (!preg_match_all('{'.$keyword.$reference.'(?:'.$separator.$reference.')*}i', $body, $matches)) {
        return [];
    }

    $references = [];
    foreach ($matches[0] as $match) {
        preg_match_all('{'.$reference.'}i', $match, $numbers);
        $references = array_merge($references, array_map('intval', $numbers[1]));
    }

    return array_values(array_unique($references));
}

/**
 * The template asks for "Fix #123" in the Issues row; a row made of bare references means the same.
 *
 * @return list<int>
 */
function issuesRowReferences(string $body, string $owner, string $project): array
{
    $row = tableRow($body, 'Issues') ?? '';
    $reference = sprintf('(?:%s/issues/|\#)(\d+)(?!\d)', preg_quote('https://github.com/'.$owner.'/'.$project, '{'));

    if (!preg_match('{^(?:'.$reference.'[\s,;&+]*)+$}', $row)) {
        return [];
    }

    preg_match_all('{'.$reference.'}', $row, $numbers);

    return array_map('intval', $numbers[1]);
}

/**
 * A PR opened from a branch of the repository itself is force-pushed like a fork, unless that branch is maintained.
 */
function isMaintainedBranch(array $state, string $branch): bool
{
    if ($branch === $state['default_branch'] || $branch === $state['branch'] || preg_match('/^\d+\.\d+(?:\.x)?$/', $branch)) {
        return true;
    }

    try {
        return api("repos/{$state['repo']}/branches/".rawurlencode($branch))['protected'];
    } catch (RuntimeException) {
        return true;
    }
}

function fetchPullRequest(string $repo, int $number): array
{
    return [
        'pull' => api("repos/$repo/pulls/$number"),
        'commits' => apiList("repos/$repo/pulls/$number/commits?per_page=100"),
        'comments' => apiList("repos/$repo/issues/$number/comments?per_page=100"),
        'reviews' => apiList("repos/$repo/pulls/$number/reviews?per_page=100"),
    ];
}

function describePullRequest(array $state, array $raw, array $options): array
{
    $pull = $raw['pull'];
    $number = $pull['number'];

    if ('open' !== $pull['state']) {
        stop(sprintf('#%d is %s.', $number, $pull['merged_at'] ? 'already merged' : 'closed'));
    }

    $asked = $pull['base']['ref'];
    if ($asked !== $state['branch'] && !isset($options['retarget'])) {
        stop(sprintf('#%d was opened for the %s branch, not %s: pass --retarget to rebase it onto %3$s.', $number, $asked, $state['branch']));
    }

    $contributors = $logins = [];
    $hasMergeCommits = false;
    foreach ($raw['commits'] as $commit) {
        if (1 < count($commit['parents'])) {
            $hasMergeCommits = true;

            // branch updates made with the GitHub UI don't make a contributor
            if ('web-flow' === ($commit['committer']['login'] ?? null)) {
                continue;
            }
        }
        $contributors[] = $commit['author']['login'] ?? $commit['commit']['author']['name'];

        // the raw name of an author is any text, which must not become a mention
        if (isset($commit['author']['login'])) {
            $logins[] = $commit['author']['login'];
        }
    }
    $contributors = array_values(array_unique($contributors));

    $count = $pull['commits'];
    $squash = match (true) {
        isset($options['no-squash']) => false,
        isset($options['squash']) => 1 < $count,
        default => 1 < $count && count($contributors) <= 1,
    };

    if (!$squash && $hasMergeCommits) {
        stop(sprintf('#%d contains merge commits and is not squashed: rebase them away first, or pass --squash.', $number));
    }

    $headRepo = $pull['head']['repo'];
    $sameRepo = $state['repo'] === ($headRepo['full_name'] ?? null);
    $editable = match (true) {
        null === $headRepo => 'its fork was deleted',
        $sameRepo && isMaintainedBranch($state, $pull['head']['ref']) => sprintf('its branch %s is a maintained branch of %s', $pull['head']['ref'], $state['repo']),
        $sameRepo => true,
        !$pull['maintainer_can_modify'] => 'the author did not allow edits by maintainers',
        'Organization' === $headRepo['owner']['type'] => 'maintainers cannot push to a fork owned by an organization',
        default => true,
    };

    $notes = '';
    if (null === $state['security']) {
        foreach ($raw['comments'] as $comment) {
            $login = $comment['user']['login'] ?? 'ghost';

            if (in_array($login, BOTS, true)) {
                continue;
            }

            $notes .= str_repeat('-', 75)."\n\nby {$login} at {$comment['created_at']}\n\n".controlFree(str_replace("\r\n", "\n", $comment['body']))."\n\n";
        }
    }

    return [
        'number' => $number,
        'title' => $pull['title'],
        'body' => str_replace("\r\n", "\n", $pull['body'] ?? ''),
        'labels' => array_column($pull['labels'], 'name'),
        'author' => $pull['user']['login'],
        'author_is_bot' => 'Bot' === $pull['user']['type'] || str_ends_with($pull['user']['login'], '[bot]'),
        'asked' => $asked,
        'retarget' => $asked !== $state['branch'],
        'head' => $pull['head']['sha'],
        'head_ref' => $pull['head']['ref'],
        'head_repo' => $headRepo['full_name'] ?? null,
        'same_repo' => $sameRepo,
        'fork_url' => str_starts_with($state['remote_url'], 'https://') ? ($headRepo['clone_url'] ?? null) : ($headRepo['ssh_url'] ?? null),
        'editable' => true === $editable,
        'not_editable_because' => true === $editable ? null : $editable,
        'commit_count' => $count,
        'contributors' => $contributors,
        'logins' => array_values(array_unique($logins)),
        'carsonbot' => count(array_filter($raw['comments'], static fn (array $comment): bool => 'carsonbot' === ($comment['user']['login'] ?? null))),
        'ci_files' => 0,
        'squash' => $squash,
        'category' => null,
        'suggested' => null,
        'comments' => substr_count($notes, "\n\nby "),
        'notes' => $notes,
        'merged_head' => null,
        'rebased_commits' => null,
        'rebasing' => false,
        'edited' => false,
        'resolved' => false,
        'merge' => null,
        'gates' => [],
        'issues' => [],
        'docs' => null,
    ];
}

/**
 * @return array{0: string, 1: string}
 */
function suggestCategory(array $pr, array $files): array
{
    $body = $pr['body'];
    $reasons = [];

    if ($files && !array_filter($files, static fn (string $file): bool => !str_contains($file, '/Tests/') && !str_starts_with($file, '.github/'))) {
        return ['tidy', 'only tests or CI files change'];
    }

    foreach (['Feature' => 'feature', 'Deprecation' => 'feature', 'Bug' => 'bug'] as $label => $category) {
        if (in_array($label, $pr['labels'], true)) {
            $reasons[$category][] = sprintf('"%s" label', $label);
        }
    }
    foreach (['New feature?' => 'feature', 'Deprecations?' => 'feature', 'Bug fix?' => 'bug'] as $question => $category) {
        if (preg_match('/^yes\b/i', tableRow($body, $question) ?? '')) {
            $reasons[$category][] = sprintf('"%s yes"', $question);
        }
    }

    foreach (['feature', 'bug'] as $category) {
        if (isset($reasons[$category])) {
            return [$category, implode(', ', $reasons[$category])];
        }
    }

    return ['minor', 'neither a bug fix nor a feature'];
}

function computeGates(array $state, array $pr, array $raw): array
{
    $pull = $raw['pull'];
    $gates = [];

    if ($pull['draft']) {
        $gates['draft'] = ['DECIDE', 'the PR is a draft'];
    }

    // votes: GitHub reviews and +1/-1 comments of the core team, the author's excluded
    $author = strtolower($pr['author']);
    $votes = [];
    foreach ($raw['comments'] as $comment) {
        $user = strtolower($comment['user']['login'] ?? '');

        if ($user === $author || !in_array($comment['author_association'], ['MEMBER', 'OWNER'], true)) {
            continue;
        }

        if (preg_match('/(?:\s|:|^)([+-]1)(?:\s|:|$)/', $comment['body'], $m)) {
            $votes[$user] = [(int) $m[1], 'comment'];
        } elseif (str_contains($comment['body'], "\u{1F44D}")) {
            $votes[$user] = [1, 'comment'];
        } elseif (str_contains($comment['body'], "\u{1F44E}")) {
            $votes[$user] = [-1, 'comment'];
        }
    }
    foreach ($raw['reviews'] as $review) {
        $user = strtolower($review['user']['login'] ?? '');

        if ('' === $user || $user === $author || !in_array($review['author_association'], ['MEMBER', 'OWNER'], true)) {
            continue;
        }

        if ('APPROVED' === $review['state']) {
            $votes[$user] = [1, 'review'];
        } elseif ('CHANGES_REQUESTED' === $review['state']) {
            $votes[$user] = [-1, 'review'];
        }
    }
    if ($state['merger_can_push'] && strtolower($state['merger']) !== $author) {
        $votes[strtolower($state['merger'])] = [1, 'merger'];
    }

    $list = [];
    foreach ($votes as $user => [$vote, $source]) {
        $list[] = sprintf('%s%d %s (%s)', 0 < $vote ? '+' : '', $vote, $user, $source);
    }
    $approvals = count(array_filter($votes, static fn (array $vote): bool => 0 < $vote[0]));
    $rejected = count($votes) > $approvals;
    $gates['approvals'] = [!$rejected && 2 <= $approvals ? 'OK' : 'DECIDE', ($rejected ? 'changes requested, ' : '').sprintf('%d of 2 approvals', $approvals).($list ? ': '.implode(', ', $list) : '')];

    $category = $pr['category'];
    if ('feature' === $category && $state['branch'] !== $state['default_branch']) {
        $gates['category'] = ['DECIDE', sprintf('features are merged into the %s branch only', $state['default_branch'])];
    }

    $milestone = $pull['milestone']['title'] ?? null;
    if (null === $milestone || $milestone === $state['branch']) {
        $gates['milestone'] = ['OK', $milestone ?? 'none'];
    } elseif (!str_starts_with($state['repo'], 'symfony/') || !preg_match('/\d+\.[x0-9]+/', $milestone) || !preg_match('/\d+\.[x0-9]+/', $state['branch'])) {
        $gates['milestone'] = ['OK', $milestone.', not a version'];
    } elseif (null === $branch = releaseInfo($milestone)['branch'] ?? null) {
        $gates['milestone'] = ['DECIDE', $milestone.', unknown on symfony.com'];
    } else {
        $gates['milestone'] = [$branch === $state['branch'] ? 'OK' : 'DECIDE', sprintf('%s, the %s branch', $milestone, $branch)];
    }

    if ('symfony/symfony' === $state['repo']) {
        $release = releaseInfo($state['branch']);
        $gates['branch'] = match (true) {
            null === $release => ['DECIDE', $state['branch'].' is unknown on symfony.com'],
            $release['is_eoled'] => ['DECIDE', $state['branch'].' reached its end of life'],
            $release['is_eomed'] && 'security' !== $category => ['DECIDE', $state['branch'].' gets security fixes only'],
            default => ['OK', $state['branch'].' is maintained'],
        };
    }

    $gates['ci'] = ciGate($state, $pr['head']);

    // the checks of a PR run the workflows of its head, so a PR that changes them decides its own CI
    if ($pr['ci_files']) {
        $gates['ci'][0] = 'OK' === $gates['ci'][0] ? 'DECIDE' : $gates['ci'][0];
        $gates['ci'][1] .= sprintf("\n    the PR changes %d file%s in .github/: its checks ran its own version of the workflows", $pr['ci_files'], 1 < $pr['ci_files'] ? 's' : '');
    }

    return $gates;
}

function ciGate(array $state, string $sha): array
{
    $repo = $state['repo'];
    $checks = [];

    foreach (apiPages("repos/$repo/commits/$sha/check-runs?per_page=100") as $page) {
        foreach ($page['check_runs'] as $run) {
            $checks[$run['name']] ??= ['completed' === $run['status'] ? $run['conclusion'] : 'pending', $run['details_url']];
        }
    }
    // the most recent status of each context comes first
    foreach (apiList("repos/$repo/commits/$sha/statuses?per_page=100") as $status) {
        $checks[$status['context']] ??= [$status['state'], $status['target_url']];
    }

    try {
        $required = api("repos/$repo/branches/".rawurlencode($state['branch']))['protection']['required_status_checks']['contexts'] ?? [];
    } catch (RuntimeException) {
        $required = [];
    }
    foreach ($required as $context) {
        $checks[$context] ??= ['pending', null];
    }

    ksort($checks);
    $passed = $skipped = 0;
    $failed = $pending = [];
    foreach ($checks as $name => [$conclusion, $url]) {
        match ($conclusion) {
            'success' => ++$passed,
            'skipped', 'neutral' => ++$skipped,
            'pending', 'queued', 'in_progress' => $pending[] = $name,
            default => $failed[] = sprintf('%s (%s): %s', $name, $conclusion, $url),
        };
    }

    $summary = sprintf('%d passed', $passed).($skipped ? sprintf(', %d skipped', $skipped) : '');
    if ($pending) {
        $summary .= sprintf(', %d pending: %s', count($pending), implode(', ', $pending));
    }
    if ($failed) {
        $summary .= sprintf(', %d failed:', count($failed))."\n    ".implode("\n    ", $failed);
    }

    return [$failed ? 'TRIAGE' : ($pending ? 'PENDING' : 'OK'), $summary];
}

/**
 * What push does with each linked issue, besides removing its Stalled label:
 *  - close: close it as completed, with a comment that names the PR and the branch;
 *  - github: nothing, since GitHub closes it when the merge lands in the default branch;
 *  - complete: it was closed as not planned, so close it as completed instead, with the same comment;
 *  - unstall: nothing more, it is closed as completed already.
 *
 * @return list<array{number: int, id: string, url: string, repo: string, stalled: bool, action: string}>
 */
function linkedIssues(array $state, array $pr): array
{
    [$owner, $name] = explode('/', $state['repo'], 2);

    $data = graphql(<<<'GRAPHQL'
        query ($owner: String!, $name: String!, $number: Int!) {
          repository(owner: $owner, name: $name) {
            pullRequest(number: $number) {
              closingIssuesReferences(first: 100) {
                nodes {
                  id number state stateReason url
                  labels(first: 100) { nodes { name } }
                  repository { nameWithOwner }
                }
              }
            }
          }
        }
        GRAPHQL, ['owner' => $owner, 'name' => $name, 'number' => $pr['number']]);

    $issues = [];
    // issues GitHub linked itself, from the sidebar or from a PR opened against the default branch
    foreach ($data['repository']['pullRequest']['closingIssuesReferences']['nodes'] ?? [] as $issue) {
        $issues[$issue['url']] = [
            'number' => $issue['number'],
            'id' => $issue['id'],
            'url' => $issue['url'],
            'repo' => $issue['repository']['nameWithOwner'],
            'state' => strtolower($issue['state']),
            'state_reason' => strtolower($issue['stateReason'] ?? ''),
            'stalled' => in_array('Stalled', array_column($issue['labels']['nodes'] ?? [], 'name'), true),
            'by_github' => true,
        ];
    }

    // GitHub turns closing keywords into linked issues only for PRs opened against the default branch
    $references = array_unique([...closingReferences($pr['body'], $owner, $name), ...issuesRowReferences($pr['body'], $owner, $name)]);
    foreach ($references as $reference) {
        if (isset($issues[sprintf('https://github.com/%s/%s/issues/%d', $owner, $name, $reference)])) {
            continue;
        }

        try {
            $issue = api("repos/{$state['repo']}/issues/$reference");
        } catch (RuntimeException) {
            continue;
        }

        // the issues endpoint also serves pull requests, which must not be closed
        if (isset($issue['pull_request'])) {
            continue;
        }

        $issues[$issue['html_url']] = [
            'number' => $issue['number'],
            'id' => $issue['node_id'],
            'url' => $issue['html_url'],
            'repo' => $state['repo'],
            'state' => $issue['state'],
            'state_reason' => $issue['state_reason'] ?? '',
            'stalled' => in_array('Stalled', array_column($issue['labels'], 'name'), true),
            'by_github' => false,
        ];
    }

    $linked = [];
    foreach ($issues as $issue) {
        // GitHub closes the issues it linked only when the merge lands in the default branch, and
        // it doesn't touch their labels, nor the issues that are closed already
        $action = match (true) {
            'open' === $issue['state'] => $issue['by_github'] && $state['branch'] === $state['default_branch'] ? 'github' : 'close',
            'not_planned' === $issue['state_reason'] => 'complete',
            $issue['stalled'] => 'unstall',
            default => null,
        };

        if (null !== $action) {
            $linked[] = ['number' => $issue['number'], 'id' => $issue['id'], 'url' => $issue['url'], 'repo' => $issue['repo'], 'stalled' => $issue['stalled'], 'action' => $action];
        }
    }
    usort($linked, static fn (array $a, array $b): int => [$a['repo'] !== $state['repo'], $a['repo'], $a['number']] <=> [$b['repo'] !== $state['repo'], $b['repo'], $b['number']]);

    return $linked;
}

function issueReference(array $state, array $issue): string
{
    return ($issue['repo'] === $state['repo'] ? '' : $issue['repo']).'#'.$issue['number'];
}

/**
 * @return array{0: string, 1: ?string}|null
 */
function docsPlan(array $state, array $pr): ?array
{
    if ('feature' !== $pr['category'] || 'symfony/symfony' !== $state['repo']) {
        return null;
    }

    if (str_contains($pr['body'], 'symfony/symfony-docs') && !str_contains($pr['body'], 'symfony/symfony-docs#...')) {
        return ['linked', 'the description links symfony/symfony-docs'];
    }

    $data = graphql(<<<'GRAPHQL'
        query ($owner: String!, $name: String!, $number: Int!) {
          repository(owner: $owner, name: $name) {
            pullRequest(number: $number) {
              timelineItems(itemTypes: [CROSS_REFERENCED_EVENT], first: 100) {
                nodes {
                  ... on CrossReferencedEvent {
                    source {
                      ... on PullRequest { state url repository { nameWithOwner } }
                      ... on Issue { url repository { nameWithOwner } }
                    }
                  }
                }
              }
            }
          }
        }
        GRAPHQL, ['owner' => 'symfony', 'name' => 'symfony', 'number' => $pr['number']]);

    // closed PRs were abandoned, closed issues mean someone already decided what to do
    $references = [];
    foreach ($data['repository']['pullRequest']['timelineItems']['nodes'] ?? [] as $event) {
        $source = $event['source'] ?? [];

        if ('symfony/symfony-docs' !== ($source['repository']['nameWithOwner'] ?? null) || 'CLOSED' === ($source['state'] ?? null)) {
            continue;
        }

        if (isset($source['state'])) {
            return ['tracked', $source['url']];
        }
        $references[] = $source['url'];
    }

    return $references ? ['tracked', $references[0]] : ['open', null];
}

/**
 * Thanks the author, or the first other contributor when the merger authored the PR.
 */
function thanks(array $state, array $pr): ?string
{
    if ($pr['author_is_bot']) {
        return null;
    }

    foreach ([$pr['author'], ...$pr['logins']] as $login) {
        if (strtolower($login) !== strtolower($state['merger']) && !str_ends_with($login, '[bot]')) {
            return sprintf('Thank you @%s.', $login);
        }
    }

    return null;
}

function mergeMessage(array $state, array $pr, string $commits): string
{
    $branch = $state['nice_branch'];
    $head = sprintf('%s #%s %s (%s)', $pr['category'], $state['security'] ?? $pr['number'], $pr['title'], implode(', ', $pr['contributors']));

    if ($pr['retarget'] && $pr['squash']) {
        $head .= sprintf("\n\nThis PR was submitted for the %s branch but it was squashed and merged into the %s branch instead.", $pr['asked'], $branch);
    } elseif ($pr['retarget']) {
        $head .= sprintf("\n\nThis PR was submitted for the %s branch but it was merged into the %s branch instead.", $pr['asked'], $branch);
    } elseif ($pr['squash']) {
        $head .= sprintf("\n\nThis PR was squashed before being merged into the %s branch.", $branch);
    } else {
        $head .= sprintf("\n\nThis PR was merged into the %s branch.", $branch);
    }

    if (null !== $state['security']) {
        $message = $head."\n";
    } else {
        $message = $head."\n\nDiscussion\n----------\n\n".$pr['title']."\n\n".$pr['body']."\n\nCommits\n-------\n\n".$commits."\n\n";
    }

    $message = str_replace('Sent using [Gush](https://github.com/gushphp/gush)', '', $message);
    $message = controlFree($message);

    // backticks keep the push from notifying the people mentioned
    return preg_replace_callback('/((?<!\w)@\w+(\w*))/', static fn (array $m): string => '`'.$m[0].'`', $message) ?? $message;
}

/**
 * The directories of the test suites to run on the merged changes.
 *
 * @return list<string>
 */
function testSuites(string $worktree, string $merge): array
{
    $suites = [];
    foreach (array_filter(explode("\n", git(['diff', '--name-only', $merge.'^1', $merge], $worktree))) as $file) {
        for ($dir = dirname($file); '.' !== $dir; $dir = dirname($dir)) {
            if (is_file("$worktree/$dir/phpunit.xml.dist")) {
                $suites[$dir] = true;
                break;
            }
        }
    }

    return array_keys($suites);
}

function assertNothingInProgress(string $worktree): void
{
    foreach (['rebase-merge', 'rebase-apply', 'MERGE_HEAD', 'CHERRY_PICK_HEAD', 'REVERT_HEAD'] as $path) {
        if (file_exists(trim(git(['rev-parse', '--path-format=absolute', '--git-path', $path], $worktree)))) {
            stop(sprintf('A git operation is in progress in %s (%s): finish or abort it first.', $worktree, $path));
        }
    }

    if ('' !== trim(git(['status', '--porcelain', '--untracked-files=no'], $worktree))) {
        stop(sprintf('%s has uncommitted changes: commit them first.', $worktree));
    }
}

function cmdPrepare(array $args): int
{
    [$positional, $options] = parseArgs($args, ['remote', 'repo', 'worktree', 'squash', 'no-squash', 'retarget', 'security', 'edit']);

    if (count($positional) < 2) {
        usage();
    }
    if (isset($options['squash'], $options['no-squash'])) {
        stop('--squash and --no-squash cannot be used together.', EXIT_USAGE);
    }

    $branch = array_shift($positional);
    $remote = is_string($options['remote'] ?? null) ? $options['remote'] : 'origin';
    [$status, $url] = gitRun(['remote', 'get-url', $remote]);
    $url = 0 === $status ? trim($url) : $remote;

    if (is_string($options['repo'] ?? null)) {
        $repo = $options['repo'];
    } elseif (null !== $repository = githubRepository($url)) {
        $repo = implode('/', $repository);
    } else {
        stop(sprintf('Cannot read the GitHub repository from "%s": pass --repo=<owner/name>.', $url), EXIT_USAGE);
    }

    $categories = CATEGORIES;
    if (in_array(strtolower(explode('/', $repo)[1] ?? ''), DOCUMENTATION_REPOS, true)) {
        $categories[] = 'documentation';
    }

    $requested = [];
    foreach ($positional as $spec) {
        if (!preg_match('/^#?(\d+)(?::(\w+))?$/', $spec, $m)) {
            stop('Invalid pull request: '.$spec, EXIT_USAGE);
        }
        if (isset($m[2]) && !in_array($m[2], $categories, true)) {
            stop(sprintf('Invalid category "%s". Should be one of "%s".', $m[2], implode('", "', $categories)), EXIT_USAGE);
        }
        $requested[(int) $m[1]] = $m[2] ?? null;
    }

    $security = null;
    if (isset($options['security'])) {
        $security = advisoryId(is_string($options['security']) ? $options['security'] : '');

        if (1 !== count($requested) || !in_array(reset($requested), [null, 'security'], true)) {
            stop('--security works with a single pull request, of category "security".', EXIT_USAGE);
        }
        $requested[key($requested)] = 'security';
    }

    $edit = null;
    if (isset($options['edit'])) {
        $edit = (int) $options['edit'];
        if (!isset($requested[$edit])) {
            stop(sprintf('--edit=%s does not name one of the pull requests to merge.', $options['edit']), EXIT_USAGE);
        }
    }

    $common = trim(git(['rev-parse', '--path-format=absolute', '--git-common-dir']));
    $worktree = is_string($options['worktree'] ?? null) ? $options['worktree'] : sprintf('%s/%s-merge-%s-%s-%s', sys_get_temp_dir(), basename($repo), $branch, date('Ymd-His'), bin2hex(random_bytes(3)));
    if (!str_starts_with($worktree, '/')) {
        $worktree = getcwd().'/'.$worktree;
    }
    $id = preg_replace('/[^\w.-]+/', '-', basename($worktree));
    $refs = 'refs/pr-merge/'.$id;

    if (file_exists($worktree)) {
        stop($worktree.' already exists.', EXIT_USAGE);
    }
    if ('' !== trim(git(['for-each-ref', $refs.'/', 'refs/notes/pr-merge/'.$id]))) {
        stop(sprintf('%s already exists: run "%s abort" on its worktree first.', $refs, script()), EXIT_USAGE);
    }

    $user = api('user');
    try {
        $canPush = in_array(api("repos/$repo/collaborators/{$user['login']}/permission")['permission'], ['admin', 'write'], true);
    } catch (RuntimeException) {
        $canPush = false;
    }
    [$status, $sign] = gitRun(['config', '--type=bool', '--get', 'commit.gpgsign']);

    $state = [
        'version' => 1,
        'repo' => $repo,
        'remote' => $remote,
        'remote_url' => $url,
        'branch' => $branch,
        'default_branch' => api("repos/$repo")['default_branch'],
        'nice_branch' => $branch,
        'security' => $security,
        'edit' => $edit,
        'merger' => $user['login'],
        'merger_can_push' => $canPush,
        'sign' => 0 === $status && 'true' === trim($sign),
        'common_dir' => $common,
        'worktree' => $worktree,
        'state_file' => null,
        'dir' => null,
        'refs' => $refs,
        'notes_ref' => 'refs/notes/pr-merge/'.$id,
        'base' => null,
        'tip' => null,
        'current' => null,
        'phase' => null,
        'prs' => [],
    ];

    $raws = [];
    foreach ($requested as $number => $category) {
        out(sprintf('Reading #%d...', $number));
        $raws[$number] = fetchPullRequest($repo, $number);
        $pr = describePullRequest($state, $raws[$number], $options);
        $pr['category'] = $category;
        $state['prs'][] = $pr;
    }

    $refspecs = ['+refs/heads/'.$branch.':'.$refs.'/base'];
    foreach (array_unique(array_column($state['prs'], 'asked')) as $asked) {
        if ($asked !== $branch) {
            $refspecs[] = '+refs/heads/'.$asked.':'.$refs.'/asked/'.$asked;
        }
    }
    foreach ($state['prs'] as $pr) {
        $refspecs[] = sprintf('+refs/pull/%d/head:%s/pull/%1$d', $pr['number'], $refs);
    }
    if (null !== lsRemote($remote, 'refs/notes/github-comments', null)) {
        $refspecs[] = '+refs/notes/github-comments:'.$state['notes_ref'];
    }
    out(sprintf('Fetching %s from %s...', $branch, $remote));

    try {
        git(['fetch', '-q', '--no-tags', $remote, ...$refspecs]);
        $state['base'] = $state['tip'] = trim(git(['rev-parse', $refs.'/base']));
        describeHeads($state, $raws);

        out(sprintf('Creating the worktree %s...', $worktree));
        // creating the directory first fails when another user of a shared temporary directory created it
        mkdir($worktree, 0o700, true);
        git(['worktree', 'add', '-q', '--detach', $worktree, $state['base']]);

        $state['worktree'] = $worktree = realpath($worktree);
        $state['state_file'] = stateFile($worktree);
        $state['dir'] = dirname($state['state_file']).'/pr-merge';
        mkdir($state['dir']);
        foreach ($state['prs'] as $i => $pr) {
            file_put_contents($state['dir']."/{$pr['number']}.notes", $pr['notes']);
            unset($state['prs'][$i]['notes']);
        }
        $state['nice_branch'] = niceBranch($branch, $state['base'], $worktree);
        saveState($state);
    } catch (Throwable $e) {
        if (is_dir($worktree) && 0 !== gitRun(['worktree', 'remove', '--force', $worktree])[0] && ['.', '..'] === scandir($worktree)) {
            rmdir($worktree);
        }
        deleteRefs($state);

        throw $e;
    }

    return processQueue($state);
}

/**
 * Checks the fetched heads, then reads what the summary needs.
 */
function describeHeads(array &$state, array $raws): void
{
    $refs = $state['refs'];

    foreach ($state['prs'] as $i => $pr) {
        $fetched = trim(git(['rev-parse', $refs.'/pull/'.$pr['number']]));

        if ($fetched !== $pr['head']) {
            stop(sprintf('The head of #%d moved while reading it (%s on GitHub, %s fetched): run prepare again.', $pr['number'], $pr['head'], $fetched));
        }
        if (isAncestor($pr['head'], $state['base'], null)) {
            stop(sprintf('#%d is already merged into %s.', $pr['number'], $state['branch']));
        }

        $pr['asked_ref'] = $pr['retarget'] ? $refs.'/asked/'.$pr['asked'] : $refs.'/base';
        $mergeBase = trim(git(['merge-base', $pr['asked_ref'], $pr['head']]));
        $files = array_filter(explode("\n", git(['diff', '--name-only', $mergeBase, $pr['head']])));
        $pr['ci_files'] = count(array_filter($files, static fn (string $file): bool => str_starts_with($file, '.github/')));
        [$suggestion, $reason] = suggestCategory($pr, $files);

        if (null === $pr['category']) {
            $pr['category'] = $suggestion;
            $pr['suggested'] = $reason;
        } elseif ($suggestion !== $pr['category'] && 'security' !== $pr['category']) {
            $pr['hint'] = sprintf('the PR looks like %s: %s', $suggestion, $reason);
        }

        $pr['gates'] = computeGates($state, $pr, $raws[$pr['number']]);
        $pr['issues'] = linkedIssues($state, $pr);
        $pr['docs'] = docsPlan($state, $pr);
        $state['prs'][$i] = $pr;
    }
}

function processQueue(array &$state): int
{
    foreach (array_keys($state['prs']) as $i) {
        if (null !== $state['prs'][$i]['merge']) {
            continue;
        }

        $state['current'] = $i;

        if (null === $state['prs'][$i]['merged_head'] && 0 !== $code = prepareHead($state, $i)) {
            return $code;
        }

        if (0 !== $code = mergePullRequest($state, $i)) {
            return $code;
        }
    }

    $state['current'] = null;
    saveState($state);
    printSummary($state);

    return 0;
}

function prepareHead(array &$state, int $i): int
{
    $pr = &$state['prs'][$i];
    $worktree = $state['worktree'];
    $head = $pr['head'];

    if ($pr['squash']) {
        $base = trim(git(['merge-base', $pr['retarget'] ? $pr['asked_ref'] : $state['tip'], $head], $worktree));
        $first = strtok(git(['rev-list', '--reverse', '--topo-order', $base.'..'.$head], $worktree), "\n");
        [$name, $email, $date] = explode("\0", rtrim(git(['log', '-1', '--format=%an%x00%ae%x00%ad', '--date=raw', $first], $worktree), "\n"));
        $tree = trim(git(['rev-parse', $head.'^{tree}'], $worktree));

        $command = ['commit-tree', $tree, '-p', $base];
        if ($state['sign']) {
            $command[] = '-S';
        }

        // the squashed commit keeps the first commit's author and date, and takes the PR title as message
        $head = trim(git($command, $worktree, controlFree($pr['title'])."\n", ['GIT_AUTHOR_NAME' => $name, 'GIT_AUTHOR_EMAIL' => $email, 'GIT_AUTHOR_DATE' => $date]));
        git(['update-ref', $state['refs'].'/squash/'.$pr['number'], $head], $worktree);
        out(sprintf('#%d: squashed %d commits into %s', $pr['number'], $pr['commit_count'], substr($head, 0, 11)));
    }

    if ($pr['retarget']) {
        out(sprintf('#%d: rebasing from %s onto %s', $pr['number'], $pr['asked'], $state['branch']));
        [$status, $stdout, $stderr] = gitRun(['rebase', '-q', '--onto', $state['base'], $pr['asked_ref'], $head], $worktree);

        if (0 !== $status) {
            if (!is_dir(trim(git(['rev-parse', '--path-format=absolute', '--git-path', 'rebase-merge'], $worktree)))) {
                stop(sprintf("Rebasing #%d failed:\n%s", $pr['number'], trim($stdout."\n".$stderr)));
            }

            $pr['rebasing'] = true;
            $state['phase'] = 'edit';
            saveState($state);

            out(trim($stdout."\n".$stderr));
            out();
            out(sprintf('Rebasing #%d onto %s stopped on conflicts in %s. Resolve them there, git add the files, then run:', $pr['number'], $state['branch'], $worktree));
            out(sprintf('  git -C %s -c core.hooksPath=/dev/null -c rerere.enabled=false rebase --continue', $worktree));
            out(sprintf('  %s continue %s', script(), $worktree));
            out(sprintf('Or drop everything with: %s abort %s', script(), $worktree));

            return EXIT_CONFLICT;
        }

        $head = trim(git(['rev-parse', 'HEAD'], $worktree));
        $pr['rebased_commits'] = (int) trim(git(['rev-list', '--count', $state['base'].'..'.$head], $worktree));
        git(['update-ref', $state['refs'].'/rebased/'.$pr['number'], $head], $worktree);
        git(['checkout', '-q', '--detach', $state['tip']], $worktree);
    }

    $pr['merged_head'] = $head;

    if ($state['edit'] === $pr['number']) {
        $state['edit'] = null;
        $state['phase'] = 'edit';
        git(['checkout', '-q', '--detach', $head], $worktree);
        saveState($state);

        out(sprintf('Stopped before merging #%d: its head %s is checked out in %s.', $pr['number'], substr($head, 0, 11), $worktree));
        out('Amend it there, keeping the contributor as author, then run:');
        out(sprintf('  %s continue %s', script(), $worktree));

        return EXIT_EDIT;
    }

    saveState($state);

    return 0;
}

function mergePullRequest(array &$state, int $i): int
{
    $pr = &$state['prs'][$i];
    $worktree = $state['worktree'];

    $commits = git(['log', '--oneline', '--no-decorate', '--no-show-signature', $state['tip'].'..'.$pr['merged_head']], $worktree);
    file_put_contents($state['dir']."/{$pr['number']}.msg", mergeMessage($state, $pr, $commits));

    out(sprintf('#%d: merging into %s', $pr['number'], $state['branch']));
    [$status, $stdout, $stderr] = gitRun(['merge', '-q', '--no-ff', '--no-commit', $pr['merged_head']], $worktree);

    if (0 === $status) {
        return commitMerge($state, $i);
    }

    $conflicts = array_filter(explode("\n", git(['diff', '--name-only', '--diff-filter=U'], $worktree)));
    if (!$conflicts) {
        stop(sprintf("Merging #%d failed:\n%s", $pr['number'], trim($stdout."\n".$stderr)));
    }

    $state['phase'] = 'conflict';
    saveState($state);

    out();
    out(sprintf('Merging #%d into %s conflicts in:', $pr['number'], $state['branch']));
    foreach ($conflicts as $file) {
        out('  '.$file);
    }
    out();
    out(sprintf('The merge is left in %s, with its message and notes ready in %s.', $worktree, $state['dir']));
    out('Either resolve the conflicts there, git add the files, then run:');
    out(sprintf('  %s continue %s', script(), $worktree));

    // exit status 1 means the PR conflicts with the branch itself, not only with an earlier merge of this run
    if (0 === gitRun(['merge-tree', '--write-tree', '--no-messages', $state['base'], $pr['merged_head']], $worktree)[0]) {
        out(sprintf('Or, since #%d conflicts only with an earlier merge of this run, abort, prepare and push the other PRs first,', $pr['number']));
        out(sprintf('then prepare #%d again, after rebasing it onto the pushed %s branch if it still conflicts.', $pr['number'], $state['branch']));
    } else {
        out('Or rebase the PR instead, so that its own commits resolve the conflicts:');
        out(sprintf('  git -C %s -c core.hooksPath=/dev/null merge --abort', $worktree));
        out(sprintf('  git -C %s -c core.hooksPath=/dev/null -c rerere.enabled=false rebase %s %s', $worktree, $state['base'], $pr['merged_head']));
        out('then resolve, git add, run the same git command with "rebase --continue" instead, and run continue as above.');
        if (!$pr['editable']) {
            out(sprintf('#%d cannot be updated on GitHub (%s), so GitHub will show it as closed either way.', $pr['number'], $pr['not_editable_because']));
        }
    }
    out(sprintf('Or drop everything with: %s abort %s', script(), $worktree));

    return EXIT_CONFLICT;
}

function commitMerge(array &$state, int $i): int
{
    $pr = &$state['prs'][$i];
    $worktree = $state['worktree'];

    git(['commit', '-q', '--file='.$state['dir']."/{$pr['number']}.msg"], $worktree);
    $merge = trim(git(['rev-parse', 'HEAD'], $worktree));

    if ('' !== file_get_contents($state['dir']."/{$pr['number']}.notes")) {
        git(['notes', '--ref='.$state['notes_ref'], 'add', '-f', '--file='.$state['dir']."/{$pr['number']}.notes", $merge], $worktree);
    }

    $pr['merge'] = $merge;
    $state['tip'] = $merge;
    $state['phase'] = null;
    saveState($state);

    return 0;
}

function cmdContinue(array $args): int
{
    [$positional] = parseArgs($args, []);
    if (1 !== count($positional)) {
        usage();
    }

    $state = loadState($positional[0]);
    $worktree = $state['worktree'];
    $i = $state['current'];

    if (null === $state['phase']) {
        stop(sprintf('Nothing to continue in %s: run status or push.', $worktree), EXIT_USAGE);
    }

    $pr = &$state['prs'][$i];

    if ('conflict' === $state['phase'] && 0 === gitRun(['rev-parse', '-q', '--verify', 'MERGE_HEAD'], $worktree)[0]) {
        if ($unmerged = trim(git(['diff', '--name-only', '--diff-filter=U'], $worktree))) {
            stop("Resolve these files and git add them first:\n".$unmerged);
        }

        $pr['resolved'] = true;
        unset($pr);
        $code = commitMerge($state, $i);
    } else {
        assertNothingInProgress($worktree);
        $head = trim(git(['rev-parse', 'HEAD'], $worktree));

        if ($head === $state['tip']) {
            stop(sprintf('The merge of #%d was aborted, but its head was not rebased: resolve the merge, rebase the PR, or run abort.', $pr['number']));
        }
        if (('conflict' === $state['phase'] || $pr['rebasing']) && !isAncestor($state['base'], $head, $worktree)) {
            stop(sprintf('HEAD is not based on %s: rebase it onto %s.', $state['branch'], $state['base']));
        }

        if ($pr['rebasing']) {
            $pr['rebased_commits'] = (int) trim(git(['rev-list', '--count', $state['base'].'..'.$head], $worktree));
        }
        $pr['edited'] = $pr['edited'] || $pr['rebasing'] || 'conflict' === $state['phase'] || $head !== $pr['merged_head'];
        $pr['rebasing'] = false;
        $pr['merged_head'] = $head;
        git(['update-ref', $state['refs'].'/edited/'.$pr['number'], $head], $worktree);
        git(['checkout', '-q', '--detach', $state['tip']], $worktree);
        $state['phase'] = null;
        unset($pr);

        $code = mergePullRequest($state, $i);
    }

    return 0 !== $code ? $code : processQueue($state);
}

function cmdStatus(array $args): int
{
    [$positional] = parseArgs($args, []);
    if (1 !== count($positional)) {
        usage();
    }

    $state = loadState($positional[0]);

    foreach ($state['prs'] as $i => $pr) {
        $raw = fetchPullRequest($state['repo'], $pr['number']);

        if (!in_array($raw['pull']['head']['sha'], [$pr['head'], $pr['merged_head']], true)) {
            out(sprintf('#%d was updated since prepare (head %s): abort and prepare again.', $pr['number'], substr($raw['pull']['head']['sha'], 0, 11)));
        }

        $state['prs'][$i]['gates'] = computeGates($state, $pr, $raw);
        $state['prs'][$i]['issues'] = linkedIssues($state, $pr);
        $state['prs'][$i]['docs'] = docsPlan($state, $pr);
    }
    saveState($state);

    if (null !== $state['phase']) {
        out(sprintf('Stopped on #%d (%s): see prepare\'s output, then run continue.', $state['prs'][$state['current']]['number'], $state['phase']));

        return 'conflict' === $state['phase'] ? EXIT_CONFLICT : EXIT_EDIT;
    }

    printSummary($state);

    return 0;
}

function printSummary(array $state): void
{
    $worktree = $state['worktree'];
    $decisions = [];

    out();
    out(sprintf('Prepared %d merge%s into %s in %s (%s/%s was at %s)', count($state['prs']), 1 < count($state['prs']) ? 's' : '', $state['branch'], $worktree, $state['remote'], $state['branch'], substr($state['base'], 0, 11)));

    foreach ($state['prs'] as $pr) {
        $n = $pr['number'];
        out();
        out(sprintf('#%d %s', $n, $pr['title']));
        out('  merge:      '.(null !== $pr['merge'] ? trim(git(['log', '-1', '--format=%h %s', $pr['merge']], $worktree)) : 'not merged, abort and prepare again'));

        if (null !== $pr['suggested']) {
            out(sprintf('  category:   %s, SUGGESTED (%s): to change it, abort and prepare again with %d:<category>', $pr['category'], $pr['suggested'], $n));
        } else {
            out('  category:   '.$pr['category'].(isset($pr['hint']) ? ' ('.$pr['hint'].')' : ''));
        }

        $authors = implode(', ', $pr['contributors']);
        out('  authors:    '.$authors.(in_array($pr['author'], $pr['contributors'], true) ? '' : ', opened by '.$pr['author']));

        $commits = sprintf('%d commit%s', $pr['commit_count'], 1 < $pr['commit_count'] ? 's' : '');
        $commits .= match (true) {
            $pr['squash'] => ', squashed',
            1 < $pr['commit_count'] && 1 < count($pr['contributors']) => ', not squashed (several authors)',
            default => ', not squashed',
        };
        if ($pr['retarget']) {
            $commits .= sprintf(', rebased from %s onto %s', $pr['asked'], $state['branch']);
        }
        if ($pr['edited']) {
            $commits .= ', edited by hand';
        }
        if ($pr['resolved']) {
            $commits .= ', conflicts resolved in the merge';
        }
        out('  commits:    '.$commits);

        if ($pr['merged_head'] === $pr['head']) {
            out('  GitHub:     nothing to update, GitHub will see the head as merged');
        } elseif ($pr['editable']) {
            out(sprintf('  GitHub:     %sforce-push %s to %s:%s (lease on %s), then wait for GitHub to see it', $pr['retarget'] ? 'change the base to '.$state['branch'].', ' : '', substr($pr['merged_head'], 0, 11), $pr['head_repo'], $pr['head_ref'], substr($pr['head'], 0, 11)));
            if ($pr['same_repo']) {
                out(sprintf('                the branch of the PR is in %s itself, which is usually a mistake', $state['repo']));
            }
            foreach (array_filter(explode("\n", git(['log', '--oneline', '--no-decorate', '--no-show-signature', $state['base'].'..'.$pr['merged_head']], $worktree))) as $line) {
                out('                '.$line);
            }
        } else {
            out(sprintf('  GitHub:     cannot update the PR (%s): GitHub will show it as closed, not merged', $pr['not_editable_because']));
            $decisions[] = sprintf('#%d shows as closed', $n);
        }

        out('  notes:      '.($pr['comments'] ? sprintf('%d comment%s, in %s', $pr['comments'], 1 < $pr['comments'] ? 's' : '', $state['notes_ref']) : 'none'));

        $issues = [];
        foreach (['close' => 'closes %s with a comment', 'complete' => 'marks %s as completed instead of not planned, with a comment', 'github' => 'GitHub closes %s', 'stalled' => 'removes the Stalled label of %s'] as $action => $format) {
            $references = array_map(static fn (array $issue): string => issueReference($state, $issue), array_filter($pr['issues'], static fn (array $issue): bool => 'stalled' === $action ? $issue['stalled'] : $action === $issue['action']));
            if ($references) {
                $issues[] = sprintf($format, implode(', ', $references));
            }
        }
        out('  issues:     '.($issues ? implode('; ', $issues) : 'none'));
        out('  carsonbot:  '.($pr['carsonbot'] ? sprintf('deletes its %d comment%s', $pr['carsonbot'], 1 < $pr['carsonbot'] ? 's' : '') : 'no comments'));

        if (null !== $pr['docs']) {
            out('  docs:       '.match ($pr['docs'][0]) {
                'open' => 'opens an issue on symfony/symfony-docs',
                'tracked' => 'already tracked by '.$pr['docs'][1],
                default => $pr['docs'][1],
            });
        }

        out('  thanks:     '.(thanks($state, $pr) ?? 'none'));

        foreach ($pr['gates'] as $name => [$verdict, $details]) {
            out(sprintf('  %-11s %s, %s', $name.':', $verdict, $details));

            if ('OK' !== $verdict) {
                $decisions[] = sprintf('#%d %s', $n, $name);
            }
        }

        if (null !== $pr['merge'] && ($pr['retarget'] || $pr['edited'] || $pr['resolved']) && $suites = testSuites($worktree, $pr['merge'])) {
            out('  tests:      the merge differs from what CI ran, run ./phpunit on '.implode(', ', $suites).' (this runs the code of the PR)');
        }
    }

    out();
    out('Merge commits:');
    out(rtrim(git(['log', '--first-parent', '--format=  %h %s', $state['base'].'..'.$state['tip']], $worktree)));
    out();
    out($decisions ? 'Needs a decision: '.implode(', ', $decisions) : 'Every gate passes.');
    out(sprintf('Push with: %s push %s', script(), $worktree));
    out(sprintf('Or drop everything with: %s abort %s', script(), $worktree));
}

function cmdPush(array $args): int
{
    [$positional, $options] = parseArgs($args, ['no-thanks', 'no-close-issues', 'no-docs-issue']);
    if (1 !== count($positional)) {
        usage();
    }

    $state = loadState($positional[0]);
    $worktree = $state['worktree'];
    $repo = $state['repo'];

    if (null !== $state['phase']) {
        stop(sprintf('#%d is not merged yet: finish it with continue first.', $state['prs'][$state['current']]['number']));
    }
    if (trim(git(['rev-parse', 'HEAD'], $worktree)) !== $state['tip']) {
        stop(sprintf('HEAD changed in %s since prepare: abort and prepare again.', $worktree));
    }
    if (count($state['prs']) !== (int) trim(git(['rev-list', '--first-parent', '--count', $state['base'].'..HEAD'], $worktree))) {
        stop('There should be exactly one new first-parent commit per pull request.');
    }

    assertRemoteBranch($state);

    if ($state['sign']) {
        [, $email] = gitRun(['config', 'user.email'], $worktree);
        // only the commits of this run: the signatures of the PRs' commits are not checked, so gpg never parses them
        $theirs = array_map(static fn (array $pr): string => '^'.$pr['head'], $state['prs']);
        foreach (array_filter(explode("\n", git(['log', '--format=%H %G? %ce', $state['base'].'..HEAD', ...$theirs], $worktree))) as $line) {
            [$sha, $signature, $committer] = explode(' ', $line, 3);

            if ($committer === trim($email) && !in_array($signature, ['G', 'U'], true)) {
                stop(sprintf('%s is not signed (%s) although commit.gpgsign is enabled: abort and prepare again once signing works.', $sha, $signature));
            }
        }
    }

    $merges = array_column($state['prs'], 'merge');
    foreach ($state['prs'] as $pr) {
        $pull = api("repos/$repo/pulls/{$pr['number']}");

        if ('open' !== $pull['state']) {
            stop(sprintf('#%d is %s now.', $pr['number'], $pull['state']));
        }
        if (!in_array($pull['head']['sha'], [$pr['head'], $pr['merged_head']], true)) {
            stop(sprintf('#%d was updated since prepare (head %s): abort and prepare again.', $pr['number'], substr($pull['head']['sha'], 0, 11)));
        }

        foreach ($merges as $merge) {
            if (isAncestor($merge, $pr['merged_head'], $worktree)) {
                stop(sprintf('The head of #%d contains %s, a merge of this run that is not pushed yet.', $pr['number'], substr($merge, 0, 11)));
            }
        }
    }

    // GitHub marks a PR as merged only when it sees its head in the branch: rewritten heads go to the PR first
    foreach ($state['prs'] as $pr) {
        if ($pr['merged_head'] !== $pr['head']) {
            syncHead($state, $pr);
        }
    }

    assertRemoteBranch($state);

    $refspecs = [$state['tip'].':refs/heads/'.$state['branch']];
    if (array_filter($state['prs'], static fn (array $pr): bool => 0 < $pr['comments'])) {
        $refspecs[] = $state['notes_ref'].':refs/notes/github-comments';
    }
    out(sprintf('Pushing %s to %s...', $state['branch'], $state['remote']));
    [$status, $stdout, $stderr] = gitRun(['push', '--atomic', '--no-follow-tags', $state['remote'], ...$refspecs], $worktree);
    out(trim($stdout."\n".$stderr));
    if (0 !== $status) {
        stop(sprintf('The push was rejected, nothing was pushed to %s. Run prepare again.', $state['branch']));
    }

    $failures = [];
    foreach ($state['prs'] as $pr) {
        try {
            closeTheLoop($state, $pr, $options);
        } catch (RuntimeException $e) {
            $failures[] = sprintf('#%d: %s', $pr['number'], $e->getMessage());
        }
    }

    cleanUp($state);
    out(sprintf('Removed %s.', $worktree));

    if ($failures) {
        out();
        out('The merges are pushed, but these steps failed and need to be done by hand:');
        foreach ($failures as $failure) {
            out('  '.$failure);
        }

        return EXIT_FAILURE;
    }

    return 0;
}

function assertRemoteBranch(array $state): void
{
    $ref = $state['refs'].'/remote';
    git(['fetch', '-q', '--no-tags', $state['remote'], '+refs/heads/'.$state['branch'].':'.$ref], $state['worktree']);

    if (!isAncestor($ref, $state['tip'], $state['worktree'])) {
        stop(sprintf('%s/%s moved since prepare: abort and prepare again.', $state['remote'], $state['branch']), EXIT_MOVED);
    }
}

function syncHead(array $state, array $pr): void
{
    $worktree = $state['worktree'];
    $n = $pr['number'];

    if (!$pr['editable']) {
        out(sprintf('#%d: not updated on GitHub, %s: GitHub will show it as closed', $n, $pr['not_editable_because']));

        return;
    }

    $commits = array_filter(explode("\n", git(['log', '--oneline', '--no-decorate', '--no-show-signature', $state['refs'].'/remote..'.$pr['merged_head']], $worktree)));

    if (!$pr['edited']) {
        $expected = $pr['retarget'] ? $pr['rebased_commits'] : 1;
        if (count($commits) !== $expected) {
            stop(sprintf("#%d: the new head brings %d commits, %d expected:\n  %s", $n, count($commits), $expected, implode("\n  ", $commits)));
        }
    }
    if ($pr['squash'] && !$pr['retarget'] && !$pr['edited'] && trim(git(['rev-parse', $pr['merged_head'].'^{tree}'], $worktree)) !== trim(git(['rev-parse', $pr['head'].'^{tree}'], $worktree))) {
        stop(sprintf('#%d: the squashed commit does not have the tree of the PR head.', $n));
    }

    $repo = $state['repo'];
    $restoreBase = false;
    if ($pr['retarget'] && api("repos/$repo/pulls/$n")['base']['ref'] !== $state['branch']) {
        out(sprintf('#%d: changing the base to %s', $n, $state['branch']));
        apiWrite('PATCH', "repos/$repo/pulls/$n", ['base' => $state['branch']]);
        $restoreBase = true;
    }

    if (lsRemote($pr['fork_url'], 'refs/heads/'.$pr['head_ref'], $worktree) !== $pr['merged_head']) {
        out(sprintf('#%d: pushing %s to %s:%s', $n, substr($pr['merged_head'], 0, 11), $pr['head_repo'], $pr['head_ref']));
        [$status, $stdout, $stderr] = gitRun(['push', '-q', '--no-follow-tags', '--force-with-lease=refs/heads/'.$pr['head_ref'].':'.$pr['head'], $pr['fork_url'], $pr['merged_head'].':refs/heads/'.$pr['head_ref']], $worktree);

        if (0 !== $status) {
            if ($restoreBase) {
                apiWrite('PATCH', "repos/$repo/pulls/$n", ['base' => $pr['asked']]);
            }
            stop(sprintf("#%d: pushing to %s failed, nothing was pushed to %s:\n%s", $n, $pr['head_repo'], $state['branch'], trim($stdout."\n".$stderr)));
        }
    }

    // pushing upstream before GitHub saw the new head would make it close the PR instead of marking it merged
    for ($try = 1; lsRemote($state['remote'], "refs/pull/$n/head", $worktree) !== $pr['merged_head']; ++$try) {
        if (GITHUB_SYNC_TRIES === $try) {
            stop(sprintf('#%d: GitHub still does not see the new head, nothing was pushed to %s: run push again later.', $n, $state['branch']));
        }
        sleep(GITHUB_SYNC_DELAY);
    }
    out(sprintf('#%d: GitHub sees the new head', $n));
}

function closeTheLoop(array $state, array $pr, array $options): void
{
    $repo = $state['repo'];
    $n = $pr['number'];

    if (!isset($options['no-thanks']) && null !== $thanks = thanks($state, $pr)) {
        apiWrite('POST', "repos/$repo/issues/$n/comments", ['body' => $thanks]);
        out(sprintf('#%d: commented "%s"', $n, $thanks));
    }

    if (!isset($options['no-close-issues'])) {
        $confirmed = array_column($pr['issues'], 'url');

        foreach (linkedIssues($state, $pr) as $issue) {
            // the description can change after the summary was confirmed
            if (!in_array($issue['url'], $confirmed, true)) {
                out(sprintf('#%d: skipped %s, which was not in the summary', $n, $issue['url']));
                continue;
            }

            if ($issue['stalled']) {
                gh(['api', '-X', 'DELETE', "repos/{$issue['repo']}/issues/{$issue['number']}/labels/Stalled"]);
                out(sprintf('#%d: removed the Stalled label of %s', $n, $issue['url']));
            }

            if (!in_array($issue['action'], ['close', 'complete'], true)) {
                continue;
            }

            $fixedBy = ($issue['repo'] === $repo ? '' : $repo).'#'.$n;
            apiWrite('POST', "repos/{$issue['repo']}/issues/{$issue['number']}/comments", ['body' => sprintf('Fixed by %s in %s.', $fixedBy, $state['nice_branch'])]);

            // unlike the REST API, this mutation also changes the reason of an issue that is closed already
            $result = graphql(<<<'GRAPHQL'
                mutation ($id: ID!) {
                  closeIssue(input: { issueId: $id, stateReason: COMPLETED }) {
                    issue { stateReason }
                  }
                }
                GRAPHQL, ['id' => $issue['id']]);

            if ('COMPLETED' !== ($result['closeIssue']['issue']['stateReason'] ?? null)) {
                stop('could not close '.$issue['url']);
            }
            out(sprintf('#%d: closed %s as completed', $n, $issue['url']));
        }
    }

    if (!isset($options['no-docs-issue']) && ['open', null] === docsPlan($state, $pr)) {
        $version = releaseInfo($state['branch'])['version'] ?? $state['nice_branch'];
        $milestone = null;
        foreach (apiList('repos/symfony/symfony-docs/milestones?per_page=100') as $candidate) {
            if ($candidate['title'] === $version) {
                $milestone = $candidate['number'];
            }
        }

        $title = function_exists('mb_strimwidth') ? mb_strimwidth($pr['title'], 0, 72, '…') : $pr['title'];
        $authors = implode(', ', array_map(static fn (string $name): string => in_array($name, $pr['logins'], true) ? '@'.$name : $name, $pr['contributors']));
        $issue = apiWrite('POST', 'repos/symfony/symfony-docs/issues', [
            'title' => $title,
            'milestone' => $milestone,
            'body' => <<<EOTXT
                | Q            | A
                | ------------ | ---
                | Feature PR   | {$repo}#{$n}
                | PR author(s) | {$authors}
                | Merged in    | {$state['nice_branch']}

                We created this issue to not forget to document this new feature. We would really appreciate if you can help us with this task. If you are not sure how to do it, please ask us and we will help you.

                To fix this issue, please create a PR against the {$state['nice_branch']} branch in the [symfony-docs repository](https://github.com/symfony/symfony-docs).

                Thank you! :smiley:
                EOTXT,
        ]);
        out(sprintf('#%d: opened %s', $n, $issue['html_url']));
    }

    // carsonbot's comments are hints for the contributor, they're useless once the PR is merged
    $deleted = 0;
    foreach (apiList("repos/$repo/issues/$n/comments?per_page=100") as $comment) {
        if ('carsonbot' === ($comment['user']['login'] ?? null)) {
            gh(['api', '-X', 'DELETE', "repos/$repo/issues/comments/{$comment['id']}"]);
            ++$deleted;
        }
    }
    if ($deleted) {
        out(sprintf('#%d: deleted %d comment%s of carsonbot', $n, $deleted, 1 < $deleted ? 's' : ''));
    }
}

function cleanUp(array $state): void
{
    git(['--git-dir='.$state['common_dir'], 'worktree', 'remove', '--force', $state['worktree']]);
    deleteRefs($state);
}

function deleteRefs(array $state): void
{
    $gitDir = '--git-dir='.$state['common_dir'];
    $refs = array_filter(explode("\n", git([$gitDir, 'for-each-ref', '--format=delete %(refname)', $state['refs'].'/', $state['notes_ref']])));
    if ($refs) {
        git([$gitDir, 'update-ref', '--stdin'], null, implode("\n", $refs)."\n");
    }
}

function cmdAbort(array $args): int
{
    [$positional] = parseArgs($args, []);
    if (1 !== count($positional)) {
        usage();
    }

    $state = loadState($positional[0]);
    cleanUp($state);
    out(sprintf('Removed %s and its refs, nothing was pushed.', $state['worktree']));

    return 0;
}

try {
    exit(match ($argv[1] ?? null) {
        'prepare' => cmdPrepare(array_slice($argv, 2)),
        'continue' => cmdContinue(array_slice($argv, 2)),
        'status' => cmdStatus(array_slice($argv, 2)),
        'push' => cmdPush(array_slice($argv, 2)),
        'abort' => cmdAbort(array_slice($argv, 2)),
        default => usage(),
    });
} catch (RuntimeException $e) {
    fwrite(\STDERR, sanitize($e->getMessage())."\n");
    exit($e->getCode() ?: EXIT_FAILURE);
} catch (Throwable $e) {
    fwrite(\STDERR, sanitize(sprintf("%s: %s in %s:%d\n", $e::class, $e->getMessage(), $e->getFile(), $e->getLine())));
    exit(EXIT_FAILURE);
}
