<?php

declare(strict_types=1);

namespace Laravel\Pao\UserFilters;

use php_user_filter;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class StderrCaptureFilter extends php_user_filter
{
    private static string $captured = '';

    /**
     * @param  resource  $in
     * @param  resource  $out
     * @param  int  $consumed
     */
    public function filter($in, $out, &$consumed, bool $closing): int // @pest-ignore-type
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            /** @var int $datalen */
            $datalen = $bucket->datalen;
            $consumed += $datalen;

            /** @var string $data */
            $data = $bucket->data;
            self::$captured .= $data;

            $bucket->data = '';
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }

    public static function output(): string
    {
        return self::$captured;
    }

    public static function reset(): void
    {
        self::$captured = '';
    }
}
