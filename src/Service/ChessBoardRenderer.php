<?php

declare(strict_types=1);

namespace App\Service;

class ChessBoardRenderer
{
    /**
     * Renders a chess board from a squares array (position => piece data).
     *
     * @param array<string, array{type?: string, side?: string, symbol?: string}|null> $squares
     */
    public function render(array $squares): string
    {
        $files = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        $lines = [];

        for ($rank = 8; $rank >= 1; $rank--) {
            $line = $rank . ' ';
            foreach ($files as $file) {
                $pos = $file . $rank;
                $piece = $squares[$pos] ?? null;
                $line .= $piece ? $piece['symbol'] . ' ' : '· ';
            }
            $lines[] = $line;
        }
        $lines[] = '  a b c d e f g h';

        return implode("\n", $lines);
    }
}
