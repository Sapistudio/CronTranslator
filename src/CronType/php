<?php
namespace SapiStudio\CronTranslator;

class CronType
{
    const TYPES         = ['Every', 'Increment', 'Multiple', 'Once'];
    public $objectType  = null;
    public $type        = null;
    public $value       = null;
    public $count       = null;
    public $increment   = null;
    public $position    = null;
    public $dropped     = false;
    
    /** CronType::__construct()*/
    private function __construct($type, $value = null, $count = null, $increment = null)
    {
        $this->type         = $type;
        $this->value        = $value;
        $this->count        = $count;
        $this->increment    = $increment;
    }
    
    /** CronType::setType() */
    public function setType($type){
        $this->objectType  = $type;
        return $this;
    }
    
    /** CronType::setPosition()*/
    public function setPosition($position){
        $this->position  = $position;
        return $this;
    }
    
    /** CronType::every() */
    public static function every()
    {
        return new static('Every');
    }

    /** CronType::increment()*/
    public static function increment($increment, $count = 1)
    {
        return new static('Increment', null, $count, $increment);
    }

    /** CronType::multiple()*/
    public static function multiple($count)
    {
        return new static('Multiple', null, $count);
    }

    /** CronType::once()*/
    public static function once($value)
    {
        return new static('Once', $value);
    }

    /** CronType::parse()*/
    public static function parse($expression)
    {
        // Parse "*".
        if ($expression === '*')
            return static::every();
        // Parse fixed values like "1".
        if (preg_match("/^[0-9]+$/", $expression))
            return static::once((int) $expression);
        // Parse multiple selected values like "1,2,5".
        if (preg_match("/^[0-9]+(,[0-9]+)+$/", $expression))
            return static::multiple(count(explode(',', $expression)));
        // Parse ranges of selected values like "1-5".
        if (preg_match("/^([0-9]+)\-([0-9]+)$/", $expression, $matches)) {
            $count = $matches[2] - $matches[1] + 1;
            return $count > 1 ? static::multiple($count) : static::once((int) $matches[1]);
        }
        // Parse incremental expressions like "*/2", "1-4/10" or "1,3/4".
        if (preg_match("/(.+)\/([0-9]+)$/", $expression, $matches)) {
            $range = static::parse($matches[1]);
            if ($range->hasType('Once', 'Every'))
                return static::Increment($matches[2]);
            if ($range->hasType('Multiple'))
                return static::Increment($matches[2], $range->count);
        }
        throw new \Exception("Failed to parse the following CRON expression: {$expression}");
    }

    /** CronType::hasType() */
    public function hasType()
    {
        return in_array($this->type, func_get_args());
    }
    
    /** CronType::format()*/
    public function format($minute = null)
    {
        switch($this->objectType){
            case "hours":
                $amOrPm = $this->value < 12 ? 'am' : 'pm';
                $hour   = $this->value === 0 ? 12 : $this->value;
                $hour   = $hour > 12 ? $hour - 12 : $hour;
                return $minute ? "{$hour}:{$minute->format()}{$amOrPm}" : "{$hour}{$amOrPm}";
                break;
            case "month":
                if ($this->value < 1 || $this->value > 12)
                    throw new \Exception();
                return $this->monthTranslator[$this->value];
                break;
            case "minute":
                return ($this->value < 10 ? '0' : '') . $this->value;
                break;
            case "weekday":
                if ($this->value < 0 || $this->value > 7)
                    throw new \Exception();
                return $this->daysTranslator[$this->value];
                break;
            case "day":
                if (in_array($this->value, [1, 21, 31])) {
                    return $this->value . 'st';
                }
                if (in_array($this->value, [2, 22])) {
                    return $this->value . 'nd';
                }
                if (in_array($this->value, [3, 23])) {
                    return $this->value . 'rd';
                }
                return $this->value . 'th';
                break;
            default:
                return;
        }
    }
}
