<?php

if ('cli' !== PHP_SAPI) {
    echo "This script can only be run from the command line.\n";
    exit(1);
}

$mainRepo = 'https://github.com/symfony/symfony';
exec('find src/ -name composer.json', $packages);

foreach ($packages as $package) {
    $package = dirname($package);

    if (str_contains($package, '/Resources/')) {
        continue;
    }

    $c = file_get_contents($package.'/.gitattributes');
    $c = preg_replace('{^/\.git.*+\n}m', '', $c);
    $c .= "/.git* export-ignore\n";
    file_put_contents($package.'/.gitattributes', $c);


    @mkdir($package.'/.github');
    file_put_contents($package.'/.github/PULL_REQUEST_TEMPLATE.md', <<<EOTXT
        Please do not submit any Pull Requests here. They will be closed.
        ---

        Please submit your PR here instead:
        {$mainRepo}

        This repository is what we call a "subtree split": a read-only subset of that main repository.
        We're looking forward to your PR there!

        EOTXT
    );

    @mkdir($package.'/.github/workflows');
    file_put_contents($package.'/.github/workflows/check-subtree-split.yml', <<<EOTXT
        name: Check subtree split

        on:
          pull_request_target:

        jobs:
          close-pull-request:
            runs-on: ubuntu-latest

            steps:
            - name: Close pull request
              uses: actions/github-script@v6
              with:
                script: |
                  if (context.repo.owner === "symfony") {
                    github.rest.issues.createComment({
                      owner: "symfony",
                      repo: context.repo.repo,
                      issue_number: context.issue.number,
                      body: `
                  Thanks for your Pull Request! We love contributions.

                  However, you should instead open your PR on the main repository:
                  {$mainRepo}

                  This repository is what we call a "subtree split": a read-only subset of that main repository.
                  We're looking forward to your PR there!
                  `
                    });

                    github.rest.pulls.update({
                      owner: "symfony",
                      repo: context.repo.repo,
                      pull_number: context.issue.number,
                      state: "closed"
                    });
                  }

        EOTXT
    );
}
