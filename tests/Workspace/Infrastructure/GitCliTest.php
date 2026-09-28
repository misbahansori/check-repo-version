<?php

declare(strict_types=1);

use App\Workspace\Infrastructure\GitCli;

const GIT_TEST_REPO_PREFIX = 'gitcli-test-';

function createTestRepository(): string
{
    $repo = sys_get_temp_dir() . '/' . GIT_TEST_REPO_PREFIX . uniqid();
    mkdir($repo, 0777, true);

    $run = fn (string $command) => shell_exec('git -C ' . escapeshellarg($repo) . " {$command} 2>&1");
    $run('init --quiet --initial-branch=main');
    $run('config user.name "Test"');
    $run('config user.email "test@example.com"');
    file_put_contents($repo . '/README.md', '# Test Repository');
    $run('add .');
    $run('commit -m "Initial commit | with a pipe" --quiet');

    return $repo;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir() . '/' . GIT_TEST_REPO_PREFIX . '*', GLOB_ONLYDIR) ?: [] as $repo) {
        deleteDirectory($repo);
    }
});

it('recognizes a repository', function () {
    $git = new GitCli();

    expect($git->isRepository(createTestRepository()))->toBeTrue()
        ->and($git->isRepository(sys_get_temp_dir()))->toBeFalse();
});

it('returns the current branch', function () {
    expect(new GitCli()->currentBranch(createTestRepository()))->toBe('main');
});

it('finds the main branch', function () {
    expect(new GitCli()->mainBranch(createTestRepository()))->toBe('main');
});

it('prefers the given main branch when it exists', function () {
    $git = new GitCli();
    $repo = createTestRepository();
    $git->createBranch($repo, 'develop');

    expect($git->mainBranch($repo, 'develop'))->toBe('develop')
        ->and($git->mainBranch($repo, 'missing'))->toBe('main');
});

it('detects uncommitted changes', function () {
    $git = new GitCli();
    $repo = createTestRepository();

    expect($git->hasUncommittedChanges($repo))->toBeFalse();

    file_put_contents($repo . '/test.txt', 'test content');

    expect($git->hasUncommittedChanges($repo))->toBeTrue();
});

it('fails to check out a missing branch', function () {
    expect(new GitCli()->checkout(createTestRepository(), 'nonexistent-branch')->successful)->toBeFalse();
});

it('creates and checks out a new branch', function () {
    $git = new GitCli();
    $repo = createTestRepository();

    expect($git->createBranch($repo, 'feature/test-branch')->successful)->toBeTrue()
        ->and($git->currentBranch($repo))->toBe('feature/test-branch')
        ->and($git->createBranch($repo, 'feature/test-branch')->successful)->toBeFalse();
});

it('fails to pull without a remote', function () {
    expect(new GitCli()->pull(createTestRepository())->successful)->toBeFalse();
});

it('lists commits for a date, keeping pipes in the message', function () {
    $git = new GitCli();
    $repo = createTestRepository();
    $commits = $git->commitsOn($repo, date('Y-m-d'));

    expect($commits)->toHaveCount(1)
        ->and($commits[0]->author)->toBe('Test')
        ->and($commits[0]->message)->toBe('Initial commit | with a pipe')
        ->and($git->commitsOn($repo, date('Y-m-d'), 'Somebody Else'))->toBe([]);
});

it('handles a path that is not a repository', function () {
    $git = new GitCli();
    $path = '/nonexistent/path';

    expect($git->currentBranch($path))->toBeNull()
        ->and($git->mainBranch($path))->toBeNull()
        ->and($git->hasUncommittedChanges($path))->toBeFalse()
        ->and($git->commitsOn($path, date('Y-m-d')))->toBe([]);
});
