<?php

declare(strict_types=1);

namespace Laravel\Pao\Laravel;

use Illuminate\Console\OutputStyle;
use Laravel\Pao\OutputCleaner;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class PaoOutputStyle extends OutputStyle
{
    private static ?OutputFormatter $formatter = null;

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $output->setDecorated(false);

        parent::__construct($input, $output);
    }

    /**
     * @param  string|iterable<string>  $messages
     */
    #[\Override]
    public function write(string|iterable $messages, bool $newline = false, int $options = 0): void
    {
        parent::write($this->clean($messages, ($options & self::OUTPUT_RAW) === 0), $newline, $options);
    }

    /**
     * @param  string|iterable<string>  $messages
     */
    #[\Override]
    public function writeln(string|iterable $messages, int $type = self::OUTPUT_NORMAL): void
    {
        parent::writeln($this->clean($messages, ($type & self::OUTPUT_RAW) === 0), $type);
    }

    /**
     * @param  string|iterable<string>  $messages
     * @return string|list<string>
     */
    private function clean(string|iterable $messages, bool $format): string|array
    {
        $strip = function (string $message) use ($format): string {
            if ($format) {
                $formatter = self::$formatter ??= new OutputFormatter(false);
                $message = (string) $formatter->format($message);
            }

            return OutputCleaner::clean($message);
        };

        if (is_string($messages)) {
            return $strip($messages);
        }

        return array_values(array_map($strip, [...$messages]));
    }
}
