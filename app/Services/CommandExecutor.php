<?php

namespace App\Services;

use Tempest\Console\Console;

final readonly class CommandExecutor
{
    public function __construct(
        private Console $console
    ) {}

    /**
     * Execute an array of commands
     *
     * @param array $commands Array of command definitions with 'name', 'command', and 'type' keys
     * @param string $workingDirectory The directory to execute commands in
     * @return bool True if all commands succeeded, false otherwise
     */
    public function executeCommands(array $commands, string $workingDirectory = ''): bool
    {
        $originalDir = getcwd();

        if ($workingDirectory) {
            chdir($workingDirectory);
        }

        foreach ($commands as $commandDef) {
            $name = $commandDef['name'] ?? 'Running command';
            $command = $commandDef['command'] ?? '';
            $type = $commandDef['type'] ?? 'shell';

            if (empty($command)) {
                continue;
            }

            $this->console->writeln('');
            $this->console->info($name);
            $this->console->writeln('');

            switch ($type) {
                case 'realtime':
                    $this->runCommandRealtime($command);
                    break;

                case 'shell':
                    $output = shell_exec($command . ' 2>&1');
                    $this->displayCommandOutput($output);
                    break;

                default:
                    $this->console->warn("Unknown command type: {$type}");
                    break;
            }
        }

        if ($workingDirectory) {
            chdir($originalDir);
        }

        return true;
    }

    /**
     * Run a command and display output in realtime
     */
    private function runCommandRealtime(string $command): void
    {
        $process = popen($command . ' 2>&1', 'r');

        if (!$process) {
            $this->console->error("Failed to execute command: {$command}");
            return;
        }

        while (!feof($process)) {
            $line = fgets($process);
            if ($line !== false && trim($line) !== '') {
                $this->console->writeln("  " . trim($line));
            }
        }

        pclose($process);
    }

    /**
     * Display command output
     */
    private function displayCommandOutput(?string $output): void
    {
        if ($output && trim($output) !== '') {
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $this->console->writeln("  {$line}");
                }
            }
        }
    }
}
