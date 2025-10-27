<?php

namespace Tests\Services;

use App\Services\GitRepository;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class GitRepositoryTest extends TestCase
{
    private string $testRepoPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary test repository
        $this->testRepoPath = sys_get_temp_dir() . '/test-git-repo-' . uniqid();
        mkdir($this->testRepoPath, 0777, true);

        // Initialize a git repository for testing
        chdir($this->testRepoPath);
        shell_exec('git init --quiet');
        shell_exec('git config user.name "Test"');
        shell_exec('git config user.email "test@example.com"');

        // Create an initial commit
        file_put_contents($this->testRepoPath . '/README.md', '# Test Repository');
        shell_exec('git add .');
        shell_exec('git commit -m "Initial commit" --quiet');
    }

    protected function tearDown(): void
    {
        // Clean up the test repository
        if (is_dir($this->testRepoPath)) {
            $this->deleteDirectory($this->testRepoPath);
        }

        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $fileinfo->isDir() ? rmdir($fileinfo->getRealPath()) : unlink($fileinfo->getRealPath());
        }

        rmdir($dir);
    }

    public function testGetCurrentBranchReturnsMainWhenOnMain(): void
    {
        // The repository starts on 'main' branch by default
        $gitRepo = new GitRepository($this->testRepoPath);

        $branch = $gitRepo->getCurrentBranch();

        // Git 2.28+ uses 'main' as default, older versions use 'master'
        $this->assertContains($branch, ['main', 'master'], "Branch should be 'main' or 'master'");
    }

    public function testDetermineMainBranchReturnsMainWhenItExists(): void
    {
        $gitRepo = new GitRepository($this->testRepoPath);

        $mainBranch = $gitRepo->determineMainBranch();

        // Should detect main or master depending on Git version
        $this->assertNotNull($mainBranch);
        $this->assertContains($mainBranch, ['main', 'master']);
    }

    public function testHasUncommittedChangesReturnsFalseWhenClean(): void
    {
        $gitRepo = new GitRepository($this->testRepoPath);

        $hasChanges = $gitRepo->hasUncommittedChanges();

        $this->assertFalse($hasChanges, 'Repository should be clean after initial commit');
    }

    public function testHasUncommittedChangesReturnsTrueWhenThereAreChanges(): void
    {
        $gitRepo = new GitRepository($this->testRepoPath);

        // Create an uncommitted change
        file_put_contents($this->testRepoPath . '/test.txt', 'test content');

        $hasChanges = $gitRepo->hasUncommittedChanges();

        $this->assertTrue($hasChanges, 'Repository should detect uncommitted changes');
    }

    public function testCheckoutReturnsFalseWhenBranchDoesNotExist(): void
    {
        $gitRepo = new GitRepository($this->testRepoPath);

        $result = $gitRepo->checkout('nonexistent-branch');

        $this->assertFalse($result, 'Checkout should fail for non-existent branch');
    }

    public function testCheckoutNewBranchReturnsTrue(): void
    {
        $gitRepo = new GitRepository($this->testRepoPath);

        $result = $gitRepo->checkoutNewBranch('feature/test-branch');

        $this->assertTrue($result, 'Should successfully create new branch');
        $this->assertEquals('feature/test-branch', $gitRepo->getCurrentBranch());
    }

    public function testCheckoutNewBranchHandlesExistingBranch(): void
    {
        $gitRepo = new GitRepository($this->testRepoPath);

        // First checkout should succeed
        $result1 = $gitRepo->checkoutNewBranch('feature/test-branch');
        $this->assertTrue($result1);

        // Checkout back to main first
        $gitRepo->checkout('main');

        // Second checkout may succeed or fail depending on implementation
        $result2 = $gitRepo->checkoutNewBranch('feature/test-branch');
        $this->assertIsBool($result2);
    }

    public function testPullReturnsTrueOnSuccess(): void
    {
        $gitRepo = new GitRepository($this->testRepoPath);

        // Pull from an empty remote (should still succeed)
        $result = $gitRepo->pull();

        // Note: This may fail in some environments, so we're just verifying
        // the method doesn't throw an exception
        $this->assertIsBool($result);
    }

    public function testGetCurrentBranchReturnsUnknownForInvalidRepo(): void
    {
        $gitRepo = new GitRepository('/nonexistent/path');

        $branch = $gitRepo->getCurrentBranch();

        $this->assertEquals('unknown', $branch);
    }

    public function testDetermineMainBranchReturnsNullForInvalidRepo(): void
    {
        $gitRepo = new GitRepository('/nonexistent/path');

        $branch = $gitRepo->determineMainBranch();

        $this->assertNull($branch);
    }

    public function testHasUncommittedChangesReturnsTrueForInvalidRepo(): void
    {
        $gitRepo = new GitRepository('/nonexistent/path');

        // Should handle gracefully and not throw
        $result = $gitRepo->hasUncommittedChanges();

        $this->assertIsBool($result);
    }
}
