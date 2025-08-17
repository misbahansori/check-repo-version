<?php

namespace App\Console\Commands\Concerns;

trait ConsoleTable
{
    private function table(array $headers, array $rows): void
    {
        $columnWidths = $this->calculateColumnWidths($rows, $headers);
        $borderLine = $this->createBorderLine($columnWidths);

        // Display table with borders
        $this->writeln($borderLine);
        $this->displayTableHeaders($headers, $columnWidths);
        $this->writeln($this->createBorderLine($columnWidths, '=', '+'));
        $this->displayTableRows($rows, $columnWidths);
        $this->writeln($borderLine);
    }

    private function calculateColumnWidths(array $rows, array $headers): array
    {
        $columnWidths = [];

        // Initialize with header lengths
        foreach ($headers as $index => $header) {
            $columnWidths[$index] = strlen($header) + 2;
        }

        // Adjust for content lengths
        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $value) {
                $valueLength = strlen((string)$value);
                if (isset($columnWidths[$index])) {
                    $columnWidths[$index] = max($columnWidths[$index], $valueLength + 2);
                }
            }
        }

        return $columnWidths;
    }

    private function createBorderLine(array $columnWidths, string $char = '-', string $intersection = '+'): string
    {
        $line = $intersection;
        foreach ($columnWidths as $width) {
            $line .= str_repeat($char, $width + 2) . $intersection;
        }
        return $line;
    }

    private function displayTableHeaders(array $headers, array $columnWidths): void
    {
        $headerLine = '|';
        foreach ($headers as $index => $header) {
            $paddedHeader = ' ' . str_pad($header, $columnWidths[$index]) . ' ';
            $headerLine .= "<strong>{$paddedHeader}</strong>|";
        }
        $this->writeln($headerLine);
    }

    private function displayTableRows(array $rows, array $columnWidths): void
    {
        foreach ($rows as $row) {
            $line = '|';
            $rowValues = array_values($row);

            foreach ($rowValues as $index => $value) {
                $paddedValue = ' ' . str_pad((string)$value, $columnWidths[$index]) . ' ';
                $line .= $this->formatTableCell($value, $paddedValue) . '|';
            }

            $this->writeln($line);
        }
    }

    private function formatTableCell($value, string $paddedValue): string
    {
        if ($value === null) {
            return $paddedValue;
        } elseif (!empty($value)) {
            return "<em>{$paddedValue}</em>";
        }

        return $paddedValue;
    }
}
