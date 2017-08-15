<?php

use Waavi\Sanitizer\Sanitizer;

class SanitizerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param $data
     * @param $rules
     * @return mixed
     */
    public function sanitize($data, $rules)
    {
        $sanitizer = new Sanitizer($data, $rules);
        return $sanitizer->sanitize();
    }

    public function test_combine_filters()
    {
        $data = [
            'name' => '  HellO EverYboDy   ',
        ];
        $rules = [
            'name' => 'trim|capitalize',
        ];
        $data = $this->sanitize($data, $rules);
        $this->assertEquals('Hello Everybody', $data['name']);
    }

    public function test_input_unchanged_if_no_filter()
    {
        $data = [
            'name' => '  HellO EverYboDy   ',
        ];
        $rules = [
            'name' => '',
        ];
        $data = $this->sanitize($data, $rules);
        $this->assertEquals('  HellO EverYboDy   ', $data['name']);
    }

    public function test_only_nested_rule_input()
    {
        $data = [
            'id' => 'nope',
            'label' => 'lol'
        ];
        $rules = [
            'data.*.id' => 'capitalize',
            'label' => 'capitalize'
        ];

        $data = $this->sanitize($data, $rules);
        $this->assertEquals('nope', $data['id']);
        $this->assertEquals('Lol', $data['label']);
    }

    public function test_nested_input()
    {
        $data = [
            'first_level' => [
                'name' => '  HellO EverYboDy   ',
            ],
            'first_level_arr' => [
                ['name' => '  HellO   '],
                [
                    'name' => '  WaTeVeR   ',
                    'list' => ["  lol  "]
                ],
            ],
        ];

        $rules = [
            'first_level.name' => 'trim|capitalize',
            'first_level_arr.*.name' => 'trim|capitalize',
            'first_level_arr.*.list.*' => 'trim|capitalize',
            'first_level_arr.*.wasad.*.wate' => 'trim|capitalize',
        ];


        $data = $this->sanitize($data, $rules);
        $this->assertEquals('Hello Everybody', $data['first_level']['name']);
        $this->assertEquals('Hello', $data['first_level_arr'][0]['name']);
        $this->assertEquals('Watever', $data['first_level_arr'][1]['name']);
        $this->assertEquals('Lol', $data['first_level_arr'][1]['list'][0]);
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function it_throws_exception_if_non_existing_filter()
    {
        $this->setExpectedException(InvalidArgumentException::class);
        $data = [
            'name' => '  HellO EverYboDy   ',
        ];
        $rules = [
            'name' => 'non-filter',
        ];
        $data = $this->sanitize($data, $rules);
    }
}
