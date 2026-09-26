<?php

declare(strict_types=1);

namespace Laravel\Pao;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class OutputCleaner
{
    public static function clean(string $output): string
    {
        $output = (string) preg_replace('/\e\][^\x07\e]*(?:\x07|\e\\\\)/', '', $output);
        $output = (string) preg_replace('/\e\[[0-?]*[ -\/]*[@-~]/', '', $output);
        $output = (string) preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $output);
        $output = (string) preg_replace('/\x{FFFD}/u', '', $output);
        $output = (string) preg_replace('/[─━│┌┐└┘├┤┬┴┼▓░▒═║╔╗╚╝╠╣╦╩╬➜▶►⚠✖✔●◆■▪→←↑↓▕⨯✕]+/u', '', $output);
        $output = (string) preg_replace('/\.{3,}/', '..', $output);
        $output = (string) preg_replace('/[ \t]+/', ' ', $output);

        return (string) preg_replace('/\n\s*\n/', "\n", $output);
    }
}
