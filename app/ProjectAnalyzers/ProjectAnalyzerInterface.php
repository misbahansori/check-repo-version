<?php

namespace App\ProjectAnalyzers;

interface ProjectAnalyzerInterface
{
    public function analyze(string $filePath): array;
}
