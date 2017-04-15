<?php
namespace Session\Lib;
class TypeHandler
{
    public static function handleStart($rule, $data)
    {
        $ruleArray = [];
        $result = [];
        foreach ($rule as $key => $value) {
            $funcrResult = true;
            $ruleArray = explode('|', $value);
            foreach ($ruleArray as $value2) {
                $result = self::center($value2, $data[$key]);
                if (!$result) {
                    $funcrResult = false;
                    break;
                }
            }
            if ($funcrResult)
                $result[$key] = $data[$key];
        }
        return $result;
    }

    private static function center($value, $data)
    {
        $valueArray = explode(':', $value);
        $result = NULL;
        if (sizeof($valueArray) == 1) {
            $func = $valueArray[0];
            $result = self::$func($data);
        }else{
            $func = $valueArray[0];
            $result = self::$func($valueArray[1], $data);
        }
        return $result;
    }

    private static function isarray($data)
    {
        return is_array($data);
    }

    private static function set($data)
    {
        return (isset($data) && !empty($data) && $data !== '' && $data != NULL) ? true : false;
    }

    private static function bool($data)
    {
        return is_bool($data);
    }

    private static function string($data)
    {
        return is_string($data);
    }

    private static function min($min, $data)
    {
        $min = (int)$min;
        return ((int)$data >= $min) ? true : false;
    }

    private static function max($max, $data)
    {
        $max = (int)$max;
        return ((int)$data <= $max) ? true : false;
    }

    private static function int($data)
    {
        return is_int($data);
    }
}