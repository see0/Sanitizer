<?php

namespace Waavi\Sanitizer;

use Closure;
use InvalidArgumentException;

class Sanitizer
{
    /**
     *  Data to sanitize
     * @var array
     */
    protected $data;

    /**
     *  Filters to apply
     * @var array
     */
    protected $rules;

    /**
     *  Available filters as $name => $classPath
     * @var array
     */
    protected $filters = [
        'capitalize' => \Waavi\Sanitizer\Filters\Capitalize::class,
        'cast' => \Waavi\Sanitizer\Filters\Cast::class,
        'escape' => \Waavi\Sanitizer\Filters\EscapeHTML::class,
        'format_date' => \Waavi\Sanitizer\Filters\FormatDate::class,
        'lowercase' => \Waavi\Sanitizer\Filters\Lowercase::class,
        'uppercase' => \Waavi\Sanitizer\Filters\Uppercase::class,
        'trim' => \Waavi\Sanitizer\Filters\Trim::class,
    ];

    /**
     *  Create a new sanitizer instance.
     *
     * @param  array $data
     * @param  array $rules Rules to be applied to each data attribute
     * @param  array $filters Available filters for this sanitizer
     * @return Sanitizer
     */
    public function __construct(array $data, array $rules, array $customFilters = [])
    {
        $this->data = $data;
        $this->rules = $this->parseRulesArray($rules);
        $this->filters = array_merge($this->filters, $customFilters);
    }

    /**
     *  Parse a rules array.
     *
     * @param  array $rules
     * @return array
     */
    protected function parseRulesArray(array $rules)
    {
        $parsedRules = [];
        foreach ($rules as $attribute => $attributeRules) {
            $attributeRulesArray = explode('|', $attributeRules);
            foreach ($attributeRulesArray as $attributeRule) {
                $parsedRule = $this->parseRuleString($attributeRule);
                if ($parsedRule) {
                    $parsedRules[$attribute][] = $parsedRule;
                }
            }
        }
        return $parsedRules;
    }

    /**
     *  Parse a rule string formatted as filterName:option1, option2 into an array formatted as [name => filterName, options => [option1, option2]]
     *
     * @param  string $rule Formatted as 'filterName:option1, option2' or just 'filterName'
     * @return array           Formatted as [name => filterName, options => [option1, option2]]. Empty array if no filter name was found.
     */
    protected function parseRuleString($rule)
    {
        if (strpos($rule, ':') !== false) {
            list($name, $options) = explode(':', $rule, 2);
            $options = array_map('trim', explode(',', $options));
        } else {
            $name = $rule;
            $options = [];
        }
        if (!$name) {
            return [];
        }
        return compact('name', 'options');
    }

    /**
     *  Apply the given filter by its name
     * @param  $name
     * @return Filter
     */
    protected function applyFilter($name, $value, $options = [])
    {
        // If the filter does not exist, throw an Exception:
        if (!isset($this->filters[$name])) {
            throw new InvalidArgumentException("No filter found by the name of $name");
        }

        $filter = $this->filters[$name];
        if ($filter instanceof Closure) {
            return call_user_func_array($filter, [$value, $options]);
        } else {
            $filter = new $filter;
            return $filter->apply($value, $options);
        }
    }

    /**
     *  Sanitize the given data
     * @return array
     */
    public function sanitize()
    {
        $sanitized = $this->data;

        foreach ($this->rules as $arr_key => $rule) {
            if (str_contains($arr_key, "*")) {
                $this->sanitize_wildcard_keys($sanitized, $arr_key, $arr_key);
            } else if ($value = array_get($sanitized, $arr_key, false)) {
                array_set($sanitized, $arr_key, $this->sanitizeAttribute($arr_key, $value));
            }
        }

        return $sanitized;
    }


    protected function sanitize_wildcard_keys(&$array, $rule_key, $key = null)
    {
        $key = is_null($key) ? $rule_key : $key;

        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if ($key == '*') {
                foreach ($array as &$item) {
                    $this->sanitize_wildcard_keys($item, $rule_key, implode(".", $keys));
                }
            }

            if (!isset($array[$key]) || !is_array($array[$key])) {
                break;
            }

            $array = &$array[$key];
        }

        $current = array_shift($keys);

        if($current == '*' && is_array($array)){
            foreach($array as &$item){
             $item =  is_string($item) ? $this->sanitizeAttribute($rule_key, $item) : $item;
            }

        }elseif (isset($array[$current])){
            $array[$current] = $this->sanitizeAttribute($rule_key, $array[$current]);

        }

        return $array;
    }

    /**
     *  Sanitize the given attribute
     *
     * @param  string $attribute Attribute name
     * @param  mixed $value Attribute value
     * @return mixed   Sanitized value
     */
    protected function sanitizeAttribute($attribute, $value)
    {
        if (isset($this->rules[$attribute])) {
            foreach ($this->rules[$attribute] as $rule) {
                $value = $this->applyFilter($rule['name'], $value, $rule['options']);
            }
        }
        return $value;
    }
}
