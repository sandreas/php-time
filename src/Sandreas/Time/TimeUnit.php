<?php

namespace Sandreas\Time;

use Exception;
use JsonSerializable;

class TimeUnit implements JsonSerializable
{

    const MILLISECOND = 1;
    const SECOND = 1000;
    const MINUTE = 60000;
    const HOUR = 3600000;

    const FORMAT_DEFAULT = "%H:%M:%S.%L";

    const PLACEHOLDER_HOUR = "H";
    const PLACEHOLDER_HOUR_INT = "h";
    const PLACEHOLDER_MINUTE = "M";
    const PLACEHOLDER_MINUTE_INT = "m";
    const PLACEHOLDER_SECOND = "S";
    const PLACEHOLDER_SECOND_INT = "s";
    const PLACEHOLDER_MILLISECOND = "L";
    const PLACEHOLDER_MILLISECOND_INT = "l";

    const REGEX_DELIMITER = '#';
    const ESCAPE_CHARACTER = '%';

    const UNIT_REFERENCE = [
        self::PLACEHOLDER_HOUR => self::HOUR,
        self::PLACEHOLDER_HOUR_INT => self::HOUR,
        self::PLACEHOLDER_MINUTE => self::MINUTE,
        self::PLACEHOLDER_MINUTE_INT => self::MINUTE,
        self::PLACEHOLDER_SECOND => self::SECOND,
        self::PLACEHOLDER_SECOND_INT => self::SECOND,
        self::PLACEHOLDER_MILLISECOND => self::MILLISECOND,
        self::PLACEHOLDER_MILLISECOND_INT => self::MILLISECOND,
    ];

    const FORMAT_REFERENCE = [
        self::PLACEHOLDER_HOUR => "%02d",
        self::PLACEHOLDER_HOUR_INT => "%d",
        self::PLACEHOLDER_MINUTE => "%02d",
        self::PLACEHOLDER_MINUTE_INT => "%d",
        self::PLACEHOLDER_SECOND => "%02d",
        self::PLACEHOLDER_SECOND_INT => "%d",
        self::PLACEHOLDER_MILLISECOND => "%03d",
        self::PLACEHOLDER_MILLISECOND_INT => "%d",

    ];

    const REGEX_MAPPING = [
        self::PLACEHOLDER_HOUR => "(?P<H>[0-9]+)",
        self::PLACEHOLDER_HOUR_INT => "(?P<h>[0-9]+)",
        self::PLACEHOLDER_MINUTE => "(?P<M>[0-9]+)",
        self::PLACEHOLDER_MINUTE_INT => "(?P<m>[0-9]+)",
        self::PLACEHOLDER_SECOND => "(?P<S>[0-9]+)",
        self::PLACEHOLDER_SECOND_INT => "(?P<s>[0-9]+)",
        self::PLACEHOLDER_MILLISECOND => "(?P<L>[0-9]+)",
        self::PLACEHOLDER_MILLISECOND_INT => "(?P<l>[0-9]+)",
    ];


    /** @var float|int */
    protected $milliseconds;

    public function __construct($value = 0, $unit = self::MILLISECOND)
    {
        $this->milliseconds = $value * $unit;
    }

    /**
     * @param $timeUnitAsString
     * @param $formatString
     *
     * @return TimeUnit
     * @throws Exception
     */
    public static function fromFormat($timeUnitAsString, $formatString = self::FORMAT_DEFAULT)
    {
        $quotedFormatString = preg_quote($formatString, static::REGEX_DELIMITER);
        $placeHolderPositions = static::parsePlaceHolderPositions($quotedFormatString);
        $basePattern = static::replacePlaceHolders($quotedFormatString, $placeHolderPositions, static::REGEX_MAPPING);

        $pattern = static::REGEX_DELIMITER . $basePattern . static::REGEX_DELIMITER;

        $previous = null;
        try {
            $regexMatches = preg_match($pattern, $timeUnitAsString, $matches);
        } catch (\Throwable $e) {
            $regexMatches = false;
            $previous = $e;
        }

        if (!$regexMatches) {
            throw new Exception('Invalid format string (no match or invalid pattern <' . $pattern . '>)', 0, $previous);
        }


        if (isset($matches[self::PLACEHOLDER_MILLISECOND]) && strlen($matches[self::PLACEHOLDER_MILLISECOND]) != 3) {
            $matches[self::PLACEHOLDER_MILLISECOND] = str_pad($matches[self::PLACEHOLDER_MILLISECOND], 3, "0");
        }

        $milliseconds = 0;
        foreach (static::UNIT_REFERENCE as $unit => $factor) {
            $milliseconds += static::toMilliseconds($matches[$unit], $factor);
        }

        return new static($milliseconds);
    }

    private static function toMilliseconds(&$value, $factor)
    {
        if (isset($value)) {
            return (int)$value * $factor;
        }

        return 0;
    }

    public function add($value, $unit = self::MILLISECOND)
    {
        $this->milliseconds += $value * $unit;
    }

    public function milliseconds()
    {
        return $this->milliseconds;
    }


    /**
     * @param $formatString
     * @return string
     * @throws Exception
     */
    public function format($formatString = self::FORMAT_DEFAULT)
    {
        $placeholderPositions = $this->parsePlaceHolderPositions($formatString);
        $timeValues = $this->buildTimeValues($placeholderPositions);

        $placeHolderReplacementValues = [];
        foreach ($placeholderPositions as $position => $placeHolder) {
            $unit = static::UNIT_REFERENCE[$placeHolder];
            $format = static::FORMAT_REFERENCE[$placeHolder];
            $placeHolderReplacementValues[$placeHolder] = sprintf($format, $timeValues[$unit]);
        }

        $prefix = ($this->milliseconds < 0) ? "-" : "";
        return $prefix . $this->replacePlaceHolders($formatString, $placeholderPositions, $placeHolderReplacementValues);
    }

    protected static function replacePlaceHolders($formatString, $placeholderPositions, $placeHolderReplacementValues)
    {
        $formatted = "";
        $lastPosition = 0;
        foreach ($placeholderPositions as $position => $placeHolder) {
            $length = $position - $lastPosition;
            $formatted .= mb_substr($formatString, $lastPosition, $length);
            $formatted .= $placeHolderReplacementValues[$placeHolder];
            $lastPosition = $position + 2;
        }

        if ($lastPosition < mb_strlen($formatString)) {
            $formatted .= mb_substr($formatString, $lastPosition);
        }
        $formatted = str_replace(static::ESCAPE_CHARACTER . static::ESCAPE_CHARACTER, static::ESCAPE_CHARACTER, $formatted);
        return $formatted;
    }

    /**
     * @param $formatString
     * @return array
     * @throws Exception
     */
    protected static function parsePlaceHolderPositions($formatString)
    {
        $runes = preg_split('//u', $formatString, -1, PREG_SPLIT_NO_EMPTY);
        $len = count($runes);
        $placeholderPositions = [];
        for ($i = 0; $i < $len; $i++) {
            if ($runes[$i] === "%") {
                if (($runes[$i + 1] ?? "") === "%") {
                    $i += 1;
                    continue;
                }
                $placeholderPositions[$i] = static::ensureValidPlaceHolder($runes[$i + 1] ?? null);
            }
        }

        return $placeholderPositions;
    }

    /**
     * @param $placeHolder
     * @return array
     * @throws Exception
     */
    protected static function ensureValidPlaceHolder($placeHolder)
    {
        if ($placeHolder === null) {
            throw new Exception("Invalid format string (placeholder <%> is not allowed - please use %% for a % sign)");
        }

        if (!isset(static::UNIT_REFERENCE[$placeHolder])) {
            throw new Exception("Invalid format string (placeholder <%" . $placeHolder . "> is not allowed)");
        }
        return $placeHolder;
    }

    protected function buildTimeValues($placeHolderPositions)
    {

        $tempMilliseconds = abs($this->milliseconds);

        $timeValues = [
            static::HOUR => 0,
            static::MINUTE => 0,
            static::SECOND => 0,
            static::MILLISECOND => 0,
        ];

        foreach ($placeHolderPositions as $placeHolder) {
            $unit = static::UNIT_REFERENCE[$placeHolder];
            $timeValues[$unit] = floor($tempMilliseconds / $unit);
            $tempMilliseconds -= $timeValues[$unit] * $unit;
        }
        return $timeValues;
    }


    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return $this->milliseconds();
    }

    public function __toString()
    {
        return (string)$this->jsonSerialize();
    }
}
