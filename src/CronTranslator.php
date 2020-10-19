<?php

namespace SapiStudio\CronTranslator;

class CronTranslator
{
    private static $extendedMap = [
        '@yearly'   => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly'  => '0 0 1 * *',
        '@weekly'   => '0 0 * * 0',
        '@daily'    => '0 0 * * *',
        '@hourly'   => '0 * * * *'
    ];
    public $expression          = null;
    public $daysTranslator      = [0 => 'Sunday',1 => 'Monday',2 => 'Tuesday',3 => 'Wednesday',4 => 'Thursday',5 => 'Friday',6 => 'Saturday',7 => 'Sunday'];
    public $monthTranslator     = [1 => 'January',2 => 'February',3 => 'March',4 => 'April',5 => 'May',6 => 'June',7 => 'July',8 => 'August',9 => 'September',10 => 'October',11 => 'November',12 => 'December'];
    public $fieldTypes          = ['minute','hours','day','month','weekday'];
    protected $parsedFields     = null;
    protected $currentType      = null;
    protected $currentObject    = null;
    protected $translation      = null;
    
    public static function translate($cron)
    {
        if (isset(self::$extendedMap[$cron])) {
            $cron = self::$extendedMap[$cron];
        }
        try {
            return self::parseFields($cron)->getTranslation();
        } catch (\Throwable $th) {
            throw new \Exception($th);
        }
    }
    
    public function getTranslation(){
        return $this->translation;
    }
    
    protected static function parseFields($cron)
    {
        return new static($cron);
    }
    
    protected function __construct($expression)
    {
        $this->expression   = $expression;
        $fields             = explode(' ', $this->expression,5);
        if(count($fields) != 5)
            throw new \Exception("Failed to parse the following CRON expression: {$expression}");
        foreach($fields as $fieldIndex => $fieldExpression){
            $this->parsedFields->{$this->fieldTypes[$fieldIndex]} = CronType::parse($fieldExpression)->setPosition($fieldIndex)->setType($this->fieldTypes[$fieldIndex]);
        }
        return $this->generateTranslation();
    }
    
    protected function generateTranslation()
    {
        $onces                      = $this->filterType('Once');
        $everys                     = $this->filterType($fields, 'Every');
        $incrementsAndMultiples     = $this->filterType($fields, 'Increment', 'Multiple');
        $firstEvery                 = (reset($everys)->position) ? reset($everys)->position->position : PHP_INT_MIN;
        $firstIncrementOrMultiple   = (reset($incrementsAndMultiples)->position) ? reset($incrementsAndMultiples)->position : PHP_INT_MAX;
        $numberOfEverysKept         = $firstIncrementOrMultiple < $firstEvery ? 0 : 1;
        foreach (array_slice($everys, $numberOfEverysKept) as $field) {
            $field->dropped = true;
        }
        $this->parsedFields = null;
        foreach(array_merge(array_slice($everys, 0, $numberOfEverysKept),$incrementsAndMultiples,array_reverse($onces)) as $fieldIndex => $fieldData){
            $this->parsedFields->{$fieldIndex} = $fieldData;
        }
        $translations = [];
        foreach($this->parsedFields as $fieldIndex => $fieldData){
            $this->currentType      = $fieldIndex;
            $this->currentObject    = $fieldData;
            $translations[]         = $this->{"translate{$fieldData->type}"}();
            $this->currentType      = null;
        }
        $this->translation = ucfirst(implode(' ', array_filter($translations)));
        return $this;
    }
    
    protected function filterType(...$types)
    {
        $fields = $this->getFieldsAsArray();
        return array_filter($fields, function ($field) use ($types) {return $field->hasType(...$types);});
    }
    
    protected function getFieldsAsArray()
    {
        foreach($this->fieldTypes as $index)
            $return[$index] = $this->parsedFields->{$index};
        return $return;
    }
    
    protected function translateEvery()
    {
        switch($this->currentType){
            case "hours":
                return ($this->parsedFields->minute->hasType('Once')) ? 'once an hour' : 'every hour';
                break;
            case "month":
                return ($this->parsedFields->day->hasType('Once')) ? 'the ' . $this->parsedFields->day->format() . ' of every month' : 'every month';
                break;
            case "minute":
                return 'every minute';
                break;
            case "weekday":
                return 'every year';
                break;
            case "day":
                return ($this->parsedFields->weekday->hasType('Once')) ? "every {$this->parsedFields->weekday->format()}" : 'every day';
                break;
            default:
                return;
        }
    }
    
    protected function translateIncrement()
    {
        switch($this->currentType){
            case "hours":
                if ($this->parsedFields->minute->hasType('Once'))
                    return $this->times($this->currentObject->count) . " every {$this->currentObject->increment} hours";
                if ($this->currentObject->count > 1)
                    return "{$this->currentObject->count} hours out of {$this->currentObject->increment}";
                if ($this->parsedFields->minute->hasType('Every'))
                    return "of every {$this->currentObject->increment} hours";
                return "every {$this->currentObject->increment} hours";
                break;
            case "month":
                return ($this->currentObject->count > 1) ? "{$this->currentObject->count} months out of {$this->currentObject->increment}" : "every {$this->currentObject->increment} months";
                break;
            case "minute":
                return ($this->currentObject->count > 1) ? $this->times($this->currentObject->count) . " every {$this->currentObject->increment} minutes" : "every {$this->currentObject->increment} minutes";
                break;
            case "weekday":
                return ($this->currentObject->count > 1) ? "{$this->currentObject->count} days of the week out of {$this->currentObject->increment}" : "every {$this->currentObject->increment} days of the week";
                break;
            case "day":
                return ($this->currentObject->count > 1) ? "{$this->currentObject->count} days out of {$this->currentObject->increment}" : "every {$this->currentObject->increment} days";
                break;
            default:
                return;
        }
    }
    
    protected function translateMultiple()
    {
        switch($this->currentType){
            case "hours":
                return ($this->parsedFields->minute->hasType('Once')) ? $this->times($this->currentObject->count) . " a day" : "{$this->currentObject->count} hours a day";
                break;
            case "month":
                return "{$this->currentObject->count} months a year";
                break;
            case "minute":
                return $this->times($this->currentObject->count) . " an hour";
                break;
            case "weekday":
                return "{$this->currentObject->count} days a week";
                break;
            case "day":
                return "{$this->currentObject->count} days a month";
                break;
            default:
                return;
        }
    }
    
    protected function translateOnce()
    {
        switch($this->currentType){
            case "hours":
                return 'at ' . $this->currentObject->format($this->parsedFields->minute->hasType('Once') ? $this->parsedFields->minute : null);
                break;
            case "month":
                return ($this->parsedFields->day->hasType('Once')) ? "on {$this->currentObject->format()} the {$this->parsedFields->day->format()}" : "on {$this->currentObject->format()}";
                break;
            case "minute":
                return ;
                break;
            case "weekday":
                if ($this->parsedFields->day->hasType('Every') && ! $this->parsedFields->day->dropped)
                    return; // DaysOfMonthField adapts to "Every Sunday".
                return "on {$this->currentObject->format()}s";
                break;
            case "day":
                if ($this->parsedFields->month->hasType('Once'))
                    return; // MonthsField adapts to "On January the 1st".
                if ($this->parsedFields->month->hasType('Every') && ! $this->parsedFields->month->dropped)
                    return; // MonthsField adapts to "The 1st of every month".
                if ($this->parsedFields->month->hasType('Every') && $this->parsedFields->month->dropped)
                    return 'on the ' . $this->currentObject->format() . ' of every month';
                return 'on the ' . $this->currentObject->format();
                break;
            default:
                return;
        }
    }

    protected function times($count)
    {
        switch ($count) {
            case 1: return 'once';
            case 2: return 'twice';
            default: return "{$count} times";
        }
    }
}
