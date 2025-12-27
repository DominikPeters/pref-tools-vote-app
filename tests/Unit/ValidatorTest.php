<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Validation\Validator;

class ValidatorTest extends TestCase
{
    public function test_required_rule_fails_on_null(): void
    {
        $validator = Validator::make(
            ['name' => null],
            ['name' => 'required']
        );

        $this->assertTrue($validator->fails());
        $this->assertNotNull($validator->getError('name'));
    }

    public function test_required_rule_fails_on_empty_string(): void
    {
        $validator = Validator::make(
            ['name' => ''],
            ['name' => 'required']
        );

        $this->assertTrue($validator->fails());
    }

    public function test_required_rule_passes_with_value(): void
    {
        $validator = Validator::make(
            ['name' => 'John'],
            ['name' => 'required']
        );

        $this->assertTrue($validator->passes());
    }

    public function test_email_rule(): void
    {
        $invalid = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => 'email']
        );
        $this->assertTrue($invalid->fails());

        $valid = Validator::make(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        );
        $this->assertTrue($valid->passes());
    }

    public function test_min_rule_for_strings(): void
    {
        $short = Validator::make(
            ['password' => 'abc'],
            ['password' => 'min:8']
        );
        $this->assertTrue($short->fails());

        $long = Validator::make(
            ['password' => 'password123'],
            ['password' => 'min:8']
        );
        $this->assertTrue($long->passes());
    }

    public function test_max_rule_for_strings(): void
    {
        $long = Validator::make(
            ['title' => str_repeat('a', 256)],
            ['title' => 'max:255']
        );
        $this->assertTrue($long->fails());

        $short = Validator::make(
            ['title' => 'Short title'],
            ['title' => 'max:255']
        );
        $this->assertTrue($short->passes());
    }

    public function test_in_rule(): void
    {
        $invalid = Validator::make(
            ['status' => 'unknown'],
            ['status' => 'in:draft,open,closed']
        );
        $this->assertTrue($invalid->fails());

        $valid = Validator::make(
            ['status' => 'open'],
            ['status' => 'in:draft,open,closed']
        );
        $this->assertTrue($valid->passes());
    }

    public function test_multiple_rules(): void
    {
        $validator = Validator::make(
            ['email' => 'short'],
            ['email' => 'required|email|min:5']
        );

        $this->assertTrue($validator->fails());
        $errors = $validator->getErrors();
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_optional_fields_skip_validation_when_empty(): void
    {
        $validator = Validator::make(
            ['email' => ''],
            ['email' => 'email'] // Not required, so empty is OK
        );

        $this->assertTrue($validator->passes());
    }

    public function test_numeric_rule(): void
    {
        $invalid = Validator::make(
            ['age' => 'twenty'],
            ['age' => 'numeric']
        );
        $this->assertTrue($invalid->fails());

        $valid = Validator::make(
            ['age' => '25'],
            ['age' => 'numeric']
        );
        $this->assertTrue($valid->passes());
    }

    public function test_boolean_rule(): void
    {
        $valid_values = [true, false, 1, 0, '1', '0'];

        foreach ($valid_values as $value) {
            $validator = Validator::make(
                ['active' => $value],
                ['active' => 'boolean']
            );
            $this->assertTrue($validator->passes(), "Value should be valid boolean: " . var_export($value, true));
        }
    }

    public function test_array_rule(): void
    {
        $invalid = Validator::make(
            ['items' => 'not an array'],
            ['items' => 'array']
        );
        $this->assertTrue($invalid->fails());

        $valid = Validator::make(
            ['items' => ['a', 'b', 'c']],
            ['items' => 'array']
        );
        $this->assertTrue($valid->passes());
    }

    public function test_get_all_errors(): void
    {
        $validator = Validator::make(
            ['email' => '', 'password' => 'short'],
            ['email' => 'required', 'password' => 'min:8']
        );

        $errors = $validator->getErrors();

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }
}
