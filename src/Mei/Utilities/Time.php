<?php

declare(strict_types=1);

namespace Mei\Utilities;

use DateInterval;
use DateTime;
use Exception;

/**
 * Class Time
 *
 * @package Mei\Utilities
 */
final class Time
{
    public const ZERO_SQLTIME = '0000-00-00 00:00:00';

    /**
     * Checks if a given DateTime object is non-zero
     *
     * @param DateTime $datetime
     *
     * @return bool true if time not 0000-00-00 00:00:00
     * @throws Exception
     */
    public static function timeIsNonZero(DateTime $datetime): bool
    {
        $time = self::fromSql(self::ZERO_SQLTIME);
        return !($datetime == $time);
    }

    /**
     * Constructs a DateTime object from an SQL time string.
     *
     * Time string format is 'Y-m-d H:i:s'
     *
     * @param string|null $str
     *
     * @return DateTime
     * @throws Exception
     */
    public static function fromSql(?string $str): DateTime
    {
        if (!$str) {
            return self::fromSql(self::ZERO_SQLTIME);
        }
        return new DateTime($str);
    }

    /**
     * Constructs a DateTime object from a UNIX timestamp.
     *
     * Unix timestamp format is 'U'
     *
     * @param mixed $str
     *
     * @return DateTime
     */
    public static function fromEpoch(mixed $str): DateTime
    {
        return DateTime::createFromFormat('U', (string)(int)$str);
    }

    public static function getEpoch(): int
    {
        return time();
    }

    /**
     * Returns the current time.
     *
     * @return DateTime
     * @throws Exception
     */
    public static function now(): DateTime
    {
        return new DateTime();
    }

    /**
     * Converts the given time to an SQL time string.
     *
     * @param DateTime $t
     * @param bool $fuzzy if true returns date only, time set to 0
     *
     * @return string
     * @throws Exception
     */
    public static function sql(DateTime $t, bool $fuzzy = false): string
    {
        if (!self::timeIsNonZero($t)) {
            return self::ZERO_SQLTIME;
        }
        $format = 'Y-m-d H:i:s';
        if ($fuzzy) {
            $format = 'Y-m-d 00:00:00';
        }
        return $t->format($format);
    }

    /**
     * Converts the given time to an RFC2822 time string.
     *
     * RFC2822 format is Thu, 21 Dec 2000 16:01:07 +0000
     *
     * @param DateTime $t
     *
     * @return string
     */
    public static function rfc2822(DateTime $t): string
    {
        return $t->format('D, d M Y H:i:s O');
    }

    /**
     * Converts the given time to an unix epoch time string.
     *
     * @param DateTime $t
     *
     * @return string
     */
    public static function epoch(DateTime $t): string
    {
        return $t->format('U');
    }

    /**
     * Creates an interval from the given time string. For example,
     *  interval('-1 day');
     *  interval('+1 year');
     *
     * @param string $s
     *
     * @return DateInterval
     */
    public static function interval(string $s): DateInterval
    {
        return DateInterval::createFromDateString($s);
    }
}
