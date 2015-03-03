<?php

class VirtualSplitter
{
    private $remote;
    private $root;
    private $branches;
    private $forkPoints;
    private $headBranch;

    public function __construct()
    {
        $this->remote = trim(`LC_ALL=C git remote -v | grep 'github.com.symfony/symfony\.git (fetch)$' | cut -f 1`);
        $this->head = trim(`git rev-parse HEAD`);
        $this->root = trim(`git rev-parse --show-toplevel`).DIRECTORY_SEPARATOR;
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function getHead()
    {
        return $this->head;
    }

    public function getBranches()
    {
        if (isset($this->branches)) {
            return $this->branches;
        }

        if (preg_match('/\n  Remote branches:.*\n(?:    [-\.\w]+\n)+/', `LC_ALL=C git remote show -n {$this->remote}`, $branches)) {
            $branches = explode("\n    ", trim($branches[0]));
            array_shift($branches);
            natsort($branches);

            return $this->branches = array_flip($branches);
        }

        return $this->branches = array();
    }

    public function getForkPoints()
    {
        if (isset($this->forkPoints)) {
            return $this->forkPoints;
        }

        $forkPoints = array();
        foreach (array_keys($this->getBranches()) as $branch) {
            $hash = explode("\n", `git merge-base {$this->remote}/{$branch} {$this->head}`);
            $forkPoints[$branch] = $hash[0];
        }

        uasort($forkPoints, function ($a, $b) {
            if ($a === $b) {
                return 0;
            }
            $hash = explode("\n", `git merge-base {$a} {$b}`);

            return $hash[0] === $a ? -1 : 1;
        });

        return $this->forkPoints = $forkPoints;
    }

    public function getHeadBranch()
    {
        if (isset($this->headBranch)) {
            return $this->headBranch;
        }

        $fp = $this->getForkPoints();

        return $this->headBranch = array_pop($fp);
    }
}

$splitter = new VirtualSplitter();

$br = $splitter->getBranches();
$fp = $splitter->getForkPoints();
$hb = $splitter->getHeadBranch();

dump(compact('br', 'fp', 'hb'));

# foreach branch
#     git archive branch | tar -x
#     if branch >= current branch
#       git apply branch.diff
#     tar
#     create composer.json repository
// See https://getcomposer.org/doc/04-schema.md#repositories
