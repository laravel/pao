<?php

declare(strict_types=1);

use Laravel\Pao\Laravel\PaoOutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

it('sets decorated to false', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);

    expect($output->isDecorated())->toBeTrue();

    new PaoOutputStyle(new ArrayInput([]), $output);

    expect($output->isDecorated())->toBeFalse();
});

it('strips ANSI codes from write output', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->write("\e[32mSuccess\e[0m");

    expect($output->fetch())->toBe('Success');
});

it('strips box-drawing characters from writeln output', function (): void {
    $output = new BufferedOutput;
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln('┌─ Name ─── Value ─┐');

    expect(trim($output->fetch()))->toBe('Name Value');
});

it('compresses dot separators', function (): void {
    $output = new BufferedOutput;
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln('Application Name ..................... Laravel');

    expect(trim($output->fetch()))->toBe('Application Name .. Laravel');
});

it('cleans migration output with style tags and dots', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $line = '2026_01_27_000002_add_current_team_id_to_users_table  2026_01_27_000002_add_current_team_id_to_users_table '
        .str_repeat('<fg=gray>.</>', 78)
        .'.................................................................................'
        .'<fg=gray> 3.74ms</> 3.74ms <fg=green;options=bold>DONE</> DONE';

    $style->writeln($line);

    expect(trim($output->fetch()))->toBe(
        '2026_01_27_000002_add_current_team_id_to_users_table 2026_01_27_000002_add_current_team_id_to_users_table .. 3.74ms 3.74ms DONE DONE',
    );
});

it('handles iterable messages', function (): void {
    $output = new BufferedOutput;
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln(['Line .... one', 'Line .... two']);

    expect($output->fetch())->toContain('Line .. one')
        ->toContain('Line .. two');
});

it('strips nested style tags', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln('<fg=red><options=bold>Error</></>');

    expect(trim($output->fetch()))->toBe('Error');
});

it('strips standard named style tags', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln('<info>ok</info> <error>bad</error> <comment>note</comment>');

    expect(trim($output->fetch()))->toBe('ok bad note');
});

it('strips ANSI and style tags together', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln("\e[32m<info>Value</info>\e[0m");

    expect(trim($output->fetch()))->toBe('Value');
});

it('preserves style tags in raw write output', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->write("\e[32m<info>Value</info>\e[0m", options: OutputInterface::OUTPUT_RAW);

    expect($output->fetch())->toBe('<info>Value</info>');
});

it('preserves style tags in raw writeln output', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln([
        '<info>first</info>',
        '<comment>second</comment>',
    ], OutputInterface::OUTPUT_RAW);

    expect($output->fetch())->toBe(
        '<info>first</info>'.PHP_EOL.'<comment>second</comment>'.PHP_EOL,
    );
});

it('collapses style-wrapped dot separators to ..', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $line = 'Label '.str_repeat('<fg=gray>.</>', 20).' Value';

    $style->writeln($line);

    expect(trim($output->fetch()))->toBe('Label .. Value');
});

it('preserves non-tag angle brackets', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln('array<int, string> and Collection<User>');

    expect(trim($output->fetch()))->toBe('array<int, string> and Collection<User>');
});

it('handles empty string', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln('');

    expect($output->fetch())->toBe(PHP_EOL);
});

it('handles empty iterable', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln([]);

    expect($output->fetch())->toBe('');
});

it('cleans iterable with style tags', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln([
        '<info>first</info> <fg=gray>.......</> a',
        '<error>second</error> <fg=gray>.......</> b',
    ]);

    expect($output->fetch())->toContain('first .. a')
        ->toContain('second .. b');
});

it('strips tags across write and writeln consistently', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->write('<info>inline</info>');
    $style->writeln(' <error>line</error>');

    expect(trim($output->fetch()))->toBe('inline line');
});

it('leaves unrecognized tag-like tokens intact', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln('<unknown>text</unknown>');

    expect(trim($output->fetch()))->toBe('<unknown>text</unknown>');
});

it('collapses multi-space runs caused by tag stripping', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln('A    <fg=gray>  </>   B');

    expect(trim($output->fetch()))->toBe('A B');
});

it('formats write output when no options are given', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->write('<info>Value</info>');

    expect($output->fetch())->toBe('Value');
});

it('formats plain output the same as normal output', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->write('<info>Value</info>', options: OutputInterface::OUTPUT_PLAIN);

    expect($output->fetch())->toBe('Value');
});

it('bypasses the formatter when raw is combined with a verbosity flag', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->write('<info>Value</info>', options: OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_NORMAL);

    expect($output->fetch())->toBe('<info>Value</info>');
});

it('bypasses the formatter when raw is combined with normal', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->write('<info>Value</info>', options: OutputInterface::OUTPUT_NORMAL | OutputInterface::OUTPUT_RAW);

    expect($output->fetch())->toBe('<info>Value</info>');
});

it('still cleans ANSI and console noise from raw output', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln("\e[32m┌── <info>Name</info>".str_repeat('.', 10)." Value ─┐\e[0m", OutputInterface::OUTPUT_RAW);

    expect(trim($output->fetch()))->toBe('<info>Name</info>.. Value');
});

it('preserves tag-like payload data in raw output', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $payload = '{"label":"<info>","url":"<https://example.com>"}';

    $style->write($payload, options: OutputInterface::OUTPUT_RAW);

    expect($output->fetch())->toBe($payload);
});

it('drops tag-like payload data outside of raw output', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->write('{"label":"<info>","url":"<https://example.com>"}');

    expect($output->fetch())->toBe('{"label":"","url":"<https://example.com>"}');
});

it('preserves style tags for raw iterable write output', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $messages = (function (): Generator {
        yield '<info>first</info>';
        yield '<comment>second</comment>';
    })();

    $style->write($messages, true, OutputInterface::OUTPUT_RAW);

    expect($output->fetch())->toBe(
        '<info>first</info>'.PHP_EOL.'<comment>second</comment>'.PHP_EOL,
    );
});

it('preserves style tags for a raw writeln string', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->writeln('<comment>Value</comment>', OutputInterface::OUTPUT_RAW);

    expect($output->fetch())->toBe('<comment>Value</comment>'.PHP_EOL);
});

it('still honors verbosity for raw output', function (): void {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $style = new PaoOutputStyle(new ArrayInput([]), $output);

    $style->write('<info>Value</info>', options: OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_DEBUG);

    expect($output->fetch())->toBe('');
});
