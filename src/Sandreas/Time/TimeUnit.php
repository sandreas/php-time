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

    const FORMAT_DEFAULT = "%H:%I:%S.%V";
    const FORMAT_H_I_S_v = "%H:%I:%S.%v";
    const UNIT_REFERENCE = [
        "H" => self::HOUR,
        "h" => self::HOUR,
        "I" => self::MINUTE,
        "i" => self::MINUTE,
        "S" => self::SECOND,
        "s" => self::SECOND,
        // milliseconds
        "V" => self::MILLISECOND,
        "v" => self::MILLISECOND,
    ];

    const FORMAT_REFERENCE = [

        "H" => "%02d",
        "h" => "%d",
        "I" => "%02d",
        "i" => "%d",
        "S" => "%02d",
        "s" => "%d",
        // milliseconds
        "V" => "%03d",
        "v" => "%d",

    ];

    const REGEX_MAPPING = [
        '%H' => '(?P<H>[0-9]+)',
        '%h' => '(?P<h>[0-9]+)',
        '%I' => '(?P<M>[0-9]+)',
        '%i' => '(?P<m>[0-9]+)',
        '%S' => '(?P<S>[0-9]+)',
        '%s' => '(?P<s>[0-9]+)',
        '%V' => '(?P<V>[0-9]+)',
        '%v' => '(?P<v>[0-9]{1,3})',
    ];
    const REGEX_DELIMITER = '#';
    protected $sprintfFormatReference = [
        "H" => "%02d",
        "h" => "%d",
        "I" => "%02d",
        "i" => "%d",
        "S" => "%02d",
        "s" => "%d",
        // milliseconds
        "V" => "%03d",
        "v" => "%d",
    ];
    protected $milliseconds;
    /**
     * @var array
     */
    protected $formatsOrder;
    /**
     * @var string
     */
    protected $vsprintfString = '';

    public function __construct($value = 0, $unit = self::MILLISECOND)
    {
        $this->milliseconds = $value * $unit;
    }

    /**
     * @param $date
     * @param $format
     *
     * @return TimeUnit
     * @throws Exception
     */
    public static function fromFormat($date, $format)
    {
        $quotedFormat = preg_quote($format, static::REGEX_DELIMITER);
        $basePattern = strtr($quotedFormat, static::REGEX_MAPPING);
        $pattern = static::REGEX_DELIMITER . $basePattern . static::REGEX_DELIMITER;

        if (!preg_match($pattern, $date, $matches)) {
            throw new Exception('Invalid format string, please use only valid placeholders');
        }

        if (isset($matches["V"]) && strlen($matches["V"]) != 3) {
            $matches["V"] = str_pad($matches["V"], 3, "0");
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

    /**
     * @param $formatString
     * @return string
     * @throws Exception
     */
    public function format($formatString)
    {
        $this->parseFormatString($formatString, $this->sprintfFormatReference);
        $usedUnits = [
            static::HOUR => false,
            static::MINUTE => false,
            static::SECOND => false,
            static::MILLISECOND => false,
        ];
        $unitsOrder = [];
        foreach ($this->formatsOrder as $format) {
            $unit = static::UNIT_REFERENCE[$format];
            $unitsOrder[] = $unit;
            $usedUnits[$unit] = true;
        }
        $tempMilliseconds = abs($this->milliseconds);
        $timeValues = [];
        foreach ($usedUnits as $unit => $isUsed) {
            if (!$isUsed) {
                $timeValues[$unit] = 0;
                continue;
            }
            $timeValues[$unit] = floor($tempMilliseconds / $unit);
            $tempMilliseconds -= $timeValues[$unit] * $unit;
        }
        $vsprintfParameters = [];
        foreach ($unitsOrder as $unit) {
            $vsprintfParameters[] = $timeValues[$unit];
        }
        $prefix = "";
        if ($this->milliseconds < 0) {
            $prefix = "-";
        }

        return $prefix . vsprintf($this->vsprintfString, $vsprintfParameters);
    }

    /**
     * @param $formatString
     * @param $formatReference
     *
     * @return void
     * @throws Exception
     */
    private function parseFormatString($formatString, $formatReference)
    {
        $this->vsprintfString = '';
        $this->formatsOrder = [];
        for ($i = 0; $i < strlen($formatString); $i++) {
            if ($formatString[$i] !== "%") {
                $this->vsprintfString .= $formatString[$i];
                continue;
            }
            $format = $formatString[++$i];
            $this->formatsOrder[] = $format;
            $this->ensureValidFormat($format, $formatReference);
            $this->vsprintfString .= $formatReference[$format];
        }
    }

    /**
     * @param $param
     * @param $formatReference
     * @throws Exception
     */
    private function ensureValidFormat($param, $formatReference)
    {
        if (!isset($formatReference[$param])) {
            throw new Exception('Invalid format string, please use only valid placeholders');
        }
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

    public function milliseconds()
    {
        return $this->milliseconds;
    }

    public function __toString()
    {
        return (string)$this->milliseconds();
    }

    /**
     * @param $formatString
     * @return array
     * @throws Exception
     */
    public function parseformatString2($formatString)
    {
        $runes = preg_split('//u', $formatString, -1, PREG_SPLIT_NO_EMPTY);
        $len = count($runes);
        $placeholderPositions = [];
        for ($i = 0; $i < $len; $i++) {
            $currentRune = $runes[$i];
            if ($currentRune === "\\") {
                $i++;
                continue;
            }

            if ($currentRune === "%") {
                $placeholderPositions[$i] = $this->ensureValidPlaceHolder($runes[$i + 1] ?? null);
            }
        }

        return $placeholderPositions;
    }

    public function buildTimeValues($placeHolderPositions)
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

//        foreach ($timeValues as $unit => $value) {
//
//            if (!in_array($unit, $placeHolderPositions, true)) {
//                continue;
//            }
//            $timeValues[$unit] = floor($tempMilliseconds / $unit);
//            $tempMilliseconds -= $timeValues[$unit] * $unit;
//        }
//
//        return $timeValues;
    }

    /**
     * @param $formatString
     * @return string
     * @throws Exception
     */
    public function format2($formatString)
    {

        $formatted = "";
        if ($this->milliseconds < 0) {
            $formatted = "-";
        }

        $placeholderPositions = $this->parseFormatString2($formatString);
        $timeValues = $this->buildTimeValues($placeholderPositions);

        $lastPosition = 0;
        foreach ($placeholderPositions as $position => $placeHolder) {
            $unit = static::UNIT_REFERENCE[$placeHolder];
            $format = static::FORMAT_REFERENCE[$placeHolder];
            $length = $position - $lastPosition;
            $formatted .= mb_substr($formatString, $lastPosition, $length);
            $formatted .= sprintf($format, $timeValues[$unit]);
            $lastPosition = $position + 2;
        }

        return $formatted;
    }


    /**
     * @param $placeHolder
     * @return array
     * @throws Exception
     */
    private function ensureValidPlaceHolder($placeHolder)
    {
        if ($placeHolder === null || !isset(static::UNIT_REFERENCE[$placeHolder])) {
            throw new Exception("Invalid format string, <" . ($placeHolder ?? "empty") . "> placeholder is not allowed");
        }
        return $placeHolder;
    }
}
