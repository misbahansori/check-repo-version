<?php

use App\Services\GitRepository;

beforeEach(function () {
    $this->testRepoPath = sys_get_temp_dir() . '/test-git-repo-' . uniqid();
    mkdir($this->testRepoPath, 0777, true);

    chdir($this->testRepoPath);
    shell_exec('git init --quiet');
    shell_exec('git config user.name "Test"');
    shell_exec('git config user.email "test@example.com"');

    file_put_contents($this->testRepoPath . '/README.md', '# Test Repository');
    shell_exec('git add .');
    shell_exec('git commit -m "Initial commit" --quiet');
});

afterEach(function () {
    if (is_dir($this->testRepoPath)) {
        deleteDirectory($this->testRepoPath);
    }
});

it('returns main or master when on main branch', function () {
    $gitRepo = new GitRepository($this->testRepoPath);

    $branch = $gitRepo->getCurrentBranch();

    expect($branch)->toBeIn(['main', 'master']);
})->covers(GitRepository::class);

it('determines main branch correctly', function () {
    $gitRepo = new GitRepository($this->testRepoPath);

    $mainBranch = $gitRepo->determineMainBranch();

    expect($mainBranch)->not->toBeNull()
        ->and($mainBranch)
        ->toBeIn(['main', 'master']);
})->covers(GitRepository::class);

it('returns false when repository is clean', function () {
    $gitRepo = new GitRepository($this->testRepoPath);

    $hasChanges = $gitRepo->hasUncommittedChanges();

    expect($hasChanges)->toBeFalse();
})->covers(GitRepository::class);

it('returns true when there are uncommitted changes', function () {
    $gitRepo = new GitRepository($this->testRepoPath);

    file_put_contents($this->testRepoPath . '/test.txt', 'test content');

    $hasChanges = $gitRepo->hasUncommittedChanges();

    expect($hasChanges)->toBeTrue();
})->covers(GitRepository::class);

it('returns false when checking out nonexistent branch', function () {
    $gitRepo = new GitRepository($this->testRepoPath);

    $result = $gitRepo->checkout('nonexistent-branch');

    expect($result)->toBeFalse();
})->covers(GitRepository::class);

it('creates and checks out new branch', function () {
    $gitRepo = new GitRepository($this->testRepoPath);

    $result = $gitRepo->checkoutNewBranch('feature/test-branch');

    expect($result)->toBeTrue()
        ->and($gitRepo->getCurrentBranch())->toBe('feature/test-branch');
})->covers(GitRepository::class);

it('handles existing branch when checking out new branch', function () {
    $gitRepo = new GitRepository($this->testRepoPath);

    $result1 = $gitRepo->checkoutNewBranch('feature/test-branch');
    expect($result1)->toBeTrue();

    $gitRepo->checkout('main');

    $result2 = $gitRepo->checkoutNewBranch('feature/test-branch');
    expect($result2)->toBeBool();
})->covers(GitRepository::class);

it('can pull from remote', function () {
    $gitRepo = new GitRepository($this->testRepoPath);

    $result = $gitRepo->pull();

    expect($result)->toBeBool();
})->covers(GitRepository::class);

it('returns unknown for invalid repository path when getting current branch', function () {
    $gitRepo = new GitRepository('/nonexistent/path');

    $branch = $gitRepo->getCurrentBranch();

    expect($branch)->toBe('unknown');
})->covers(GitRepository::class);

it('returns null for invalid repository when determining main branch', function () {
    $gitRepo = new GitRepository('/nonexistent/path');

    $branch = $gitRepo->determineMainBranch();

    expect($branch)->toBeNull();
})->covers(GitRepository::class);

it('handles invalid repository gracefully for uncommitted changes', function () {
    $gitRepo = new GitRepository('/nonexistent/path');

    $result = $gitRepo->hasUncommittedChanges();

    expect($result)->toBeBool();
})->covers(GitRepository::class);
