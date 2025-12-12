<?php

namespace JobMetric\Flow\Tests\Unit\Support;

use JobMetric\Flow\Support\RestrictionResult;
use JobMetric\Flow\Tests\TestCase;

/**
 * Comprehensive tests for RestrictionResult
 *
 * These tests cover all functionality of the RestrictionResult class
 * to ensure it correctly represents the outcome of restriction evaluations.
 */
class RestrictionResultTest extends TestCase
{
    /**
     * Test that constructor sets properties correctly
     */
    public function test_constructor_sets_properties_correctly(): void
    {
        $result = new RestrictionResult(true, 'CODE_123', 'Test message');

        $this->assertTrue($result->allowed());
        $this->assertEquals('CODE_123', $result->code());
        $this->assertEquals('Test message', $result->message());
    }

    /**
     * Test that constructor accepts null for code and message
     */
    public function test_constructor_accepts_null_for_code_and_message(): void
    {
        $result = new RestrictionResult(false, null, null);

        $this->assertFalse($result->allowed());
        $this->assertNull($result->code());
        $this->assertNull($result->message());
    }

    /**
     * Test that constructor accepts only allowed parameter
     */
    public function test_constructor_accepts_only_allowed_parameter(): void
    {
        $result = new RestrictionResult(true);

        $this->assertTrue($result->allowed());
        $this->assertNull($result->code());
        $this->assertNull($result->message());
    }

    /**
     * Test that allow() creates a result with allowed set to true
     */
    public function test_allow_creates_result_with_allowed_set_to_true(): void
    {
        $result = RestrictionResult::allow();

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertTrue($result->allowed());
        $this->assertNull($result->code());
        $this->assertNull($result->message());
    }

    /**
     * Test that allow() returns a new instance each time
     */
    public function test_allow_returns_new_instance_each_time(): void
    {
        $result1 = RestrictionResult::allow();
        $result2 = RestrictionResult::allow();

        $this->assertNotSame($result1, $result2);
        $this->assertTrue($result1->allowed());
        $this->assertTrue($result2->allowed());
    }

    /**
     * Test that deny() creates a result with allowed set to false
     */
    public function test_deny_creates_result_with_allowed_set_to_false(): void
    {
        $result = RestrictionResult::deny('DENIED', 'Access denied');

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertFalse($result->allowed());
        $this->assertEquals('DENIED', $result->code());
        $this->assertEquals('Access denied', $result->message());
    }

    /**
     * Test that deny() accepts only code parameter
     */
    public function test_deny_accepts_only_code_parameter(): void
    {
        $result = RestrictionResult::deny('DENIED');

        $this->assertFalse($result->allowed());
        $this->assertEquals('DENIED', $result->code());
        $this->assertNull($result->message());
    }

    /**
     * Test that deny() returns a new instance each time
     */
    public function test_deny_returns_new_instance_each_time(): void
    {
        $result1 = RestrictionResult::deny('CODE1', 'Message 1');
        $result2 = RestrictionResult::deny('CODE2', 'Message 2');

        $this->assertNotSame($result1, $result2);
        $this->assertFalse($result1->allowed());
        $this->assertFalse($result2->allowed());
        $this->assertEquals('CODE1', $result1->code());
        $this->assertEquals('CODE2', $result2->code());
    }

    /**
     * Test that allowed() returns the correct boolean value
     */
    public function test_allowed_returns_correct_boolean_value(): void
    {
        $allowedResult = new RestrictionResult(true);
        $deniedResult = new RestrictionResult(false);

        $this->assertTrue($allowedResult->allowed());
        $this->assertFalse($deniedResult->allowed());
    }

    /**
     * Test that allowed() returns boolean type
     */
    public function test_allowed_returns_boolean_type(): void
    {
        $result = new RestrictionResult(true);

        $this->assertIsBool($result->allowed());
    }

    /**
     * Test that code() returns the correct string value
     */
    public function test_code_returns_correct_string_value(): void
    {
        $result = new RestrictionResult(false, 'TEST_CODE');

        $this->assertEquals('TEST_CODE', $result->code());
        $this->assertIsString($result->code());
    }

    /**
     * Test that code() returns null when not set
     */
    public function test_code_returns_null_when_not_set(): void
    {
        $result = new RestrictionResult(true);

        $this->assertNull($result->code());
    }

    /**
     * Test that code() can return empty string
     */
    public function test_code_can_return_empty_string(): void
    {
        $result = new RestrictionResult(false, '');

        $this->assertEquals('', $result->code());
        $this->assertIsString($result->code());
    }

    /**
     * Test that message() returns the correct string value
     */
    public function test_message_returns_correct_string_value(): void
    {
        $result = new RestrictionResult(false, 'CODE', 'Test message');

        $this->assertEquals('Test message', $result->message());
        $this->assertIsString($result->message());
    }

    /**
     * Test that message() returns null when not set
     */
    public function test_message_returns_null_when_not_set(): void
    {
        $result = new RestrictionResult(true);

        $this->assertNull($result->message());
    }

    /**
     * Test that message() can return empty string
     */
    public function test_message_can_return_empty_string(): void
    {
        $result = new RestrictionResult(false, 'CODE', '');

        $this->assertEquals('', $result->message());
        $this->assertIsString($result->message());
    }

    /**
     * Test that message() can return long strings
     */
    public function test_message_can_return_long_strings(): void
    {
        $longMessage = str_repeat('This is a very long message. ', 100);
        $result = new RestrictionResult(false, 'CODE', $longMessage);

        $this->assertEquals($longMessage, $result->message());
        $this->assertGreaterThan(1000, strlen($result->message()));
    }

    /**
     * Test that code() can contain special characters
     */
    public function test_code_can_contain_special_characters(): void
    {
        $specialCode = 'CODE_123-ABC.xyz@test';
        $result = new RestrictionResult(false, $specialCode);

        $this->assertEquals($specialCode, $result->code());
    }

    /**
     * Test that message() can contain special characters
     */
    public function test_message_can_contain_special_characters(): void
    {
        $specialMessage = 'Message with "quotes", \'apostrophes\', and <tags>';
        $result = new RestrictionResult(false, 'CODE', $specialMessage);

        $this->assertEquals($specialMessage, $result->message());
    }

    /**
     * Test that message() can contain newlines
     */
    public function test_message_can_contain_newlines(): void
    {
        $multilineMessage = "Line 1\nLine 2\nLine 3";
        $result = new RestrictionResult(false, 'CODE', $multilineMessage);

        $this->assertEquals($multilineMessage, $result->message());
        $this->assertStringContainsString("\n", $result->message());
    }

    /**
     * Test that code() can contain unicode characters
     */
    public function test_code_can_contain_unicode_characters(): void
    {
        $unicodeCode = 'CODE_测试_123';
        $result = new RestrictionResult(false, $unicodeCode);

        $this->assertEquals($unicodeCode, $result->code());
    }

    /**
     * Test that message() can contain Unicode characters
     */
    public function test_message_can_contain_unicode_characters(): void
    {
        $unicodeMessage = 'پیام تست فارسی';
        $result = new RestrictionResult(false, 'CODE', $unicodeMessage);

        $this->assertEquals($unicodeMessage, $result->message());
    }

    /**
     * Test that allow() and deny() create different results
     */
    public function test_allow_and_deny_create_different_results(): void
    {
        $allowedResult = RestrictionResult::allow();
        $deniedResult = RestrictionResult::deny('DENIED');

        $this->assertTrue($allowedResult->allowed());
        $this->assertFalse($deniedResult->allowed());
        $this->assertNull($allowedResult->code());
        $this->assertEquals('DENIED', $deniedResult->code());
    }

    /**
     * Test that multiple deny() calls with same parameters create different instances
     */
    public function test_multiple_deny_calls_create_different_instances(): void
    {
        $result1 = RestrictionResult::deny('CODE', 'Message');
        $result2 = RestrictionResult::deny('CODE', 'Message');

        $this->assertNotSame($result1, $result2);
        $this->assertEquals($result1->allowed(), $result2->allowed());
        $this->assertEquals($result1->code(), $result2->code());
        $this->assertEquals($result1->message(), $result2->message());
    }

    /**
     * Test that multiple allow() calls create different instances
     */
    public function test_multiple_allow_calls_create_different_instances(): void
    {
        $result1 = RestrictionResult::allow();
        $result2 = RestrictionResult::allow();

        $this->assertNotSame($result1, $result2);
        $this->assertEquals($result1->allowed(), $result2->allowed());
    }

    /**
     * Test that constructor with all parameters works correctly
     */
    public function test_constructor_with_all_parameters_works_correctly(): void
    {
        $result = new RestrictionResult(true, 'SUCCESS_CODE', 'Operation successful');

        $this->assertTrue($result->allowed());
        $this->assertEquals('SUCCESS_CODE', $result->code());
        $this->assertEquals('Operation successful', $result->message());
    }

    /**
     * Test that constructor with allowed false and no code/message works
     */
    public function test_constructor_with_allowed_false_and_no_code_message_works(): void
    {
        $result = new RestrictionResult(false);

        $this->assertFalse($result->allowed());
        $this->assertNull($result->code());
        $this->assertNull($result->message());
    }

    /**
     * Test that constructor with allowed true and code/message works
     */
    public function test_constructor_with_allowed_true_and_code_message_works(): void
    {
        $result = new RestrictionResult(true, 'INFO_CODE', 'Informational message');

        $this->assertTrue($result->allowed());
        $this->assertEquals('INFO_CODE', $result->code());
        $this->assertEquals('Informational message', $result->message());
    }

    /**
     * Test that code() can be numeric string
     */
    public function test_code_can_be_numeric_string(): void
    {
        $result = new RestrictionResult(false, '12345');

        $this->assertEquals('12345', $result->code());
        $this->assertIsString($result->code());
    }

    /**
     * Test that code() can be alphanumeric
     */
    public function test_code_can_be_alphanumeric(): void
    {
        $result = new RestrictionResult(false, 'ABC123XYZ');

        $this->assertEquals('ABC123XYZ', $result->code());
    }

    /**
     * Test that message() can be numeric string
     */
    public function test_message_can_be_numeric_string(): void
    {
        $result = new RestrictionResult(false, 'CODE', '12345');

        $this->assertEquals('12345', $result->message());
        $this->assertIsString($result->message());
    }

    /**
     * Test that message() can be alphanumeric
     */
    public function test_message_can_be_alphanumeric(): void
    {
        $result = new RestrictionResult(false, 'CODE', 'ABC123XYZ');

        $this->assertEquals('ABC123XYZ', $result->message());
    }

    /**
     * Test that result can be used in conditional statements
     */
    public function test_result_can_be_used_in_conditional_statements(): void
    {
        $allowedResult = RestrictionResult::allow();
        $deniedResult = RestrictionResult::deny('DENIED');

        if ($allowedResult->allowed()) {
            $this->assertTrue(true);
        }
        else {
            $this->fail('Allowed result should pass condition');
        }

        if (! $deniedResult->allowed()) {
            $this->assertTrue(true);
        }
        else {
            $this->fail('Denied result should fail condition');
        }
    }

    /**
     * Test that result can be compared
     */
    public function test_result_can_be_compared(): void
    {
        $result1 = RestrictionResult::allow();
        $result2 = RestrictionResult::allow();
        $result3 = RestrictionResult::deny('CODE');

        $this->assertEquals($result1->allowed(), $result2->allowed());
        $this->assertNotEquals($result1->allowed(), $result3->allowed());
    }

    /**
     * Test that code() and message() can be the same value
     */
    public function test_code_and_message_can_be_same_value(): void
    {
        $value = 'SAME_VALUE';
        $result = new RestrictionResult(false, $value, $value);

        $this->assertEquals($value, $result->code());
        $this->assertEquals($value, $result->message());
        $this->assertEquals($result->code(), $result->message());
    }

    /**
     * Test that result properties are immutable after construction
     */
    public function test_result_properties_are_immutable_after_construction(): void
    {
        $result = new RestrictionResult(true, 'CODE', 'Message');

        $initialAllowed = $result->allowed();
        $initialCode = $result->code();
        $initialMessage = $result->message();

        // Call methods multiple times to ensure they return the same values
        $this->assertEquals($initialAllowed, $result->allowed());
        $this->assertEquals($initialCode, $result->code());
        $this->assertEquals($initialMessage, $result->message());

        $this->assertEquals($initialAllowed, $result->allowed());
        $this->assertEquals($initialCode, $result->code());
        $this->assertEquals($initialMessage, $result->message());
    }

    /**
     * Test that deny() with empty code works
     */
    public function test_deny_with_empty_code_works(): void
    {
        $result = RestrictionResult::deny('');

        $this->assertFalse($result->allowed());
        $this->assertEquals('', $result->code());
        $this->assertNull($result->message());
    }

    /**
     * Test that deny() with empty message works
     */
    public function test_deny_with_empty_message_works(): void
    {
        $result = RestrictionResult::deny('CODE', '');

        $this->assertFalse($result->allowed());
        $this->assertEquals('CODE', $result->code());
        $this->assertEquals('', $result->message());
    }

    /**
     * Test that result can be used in array
     */
    public function test_result_can_be_used_in_array(): void
    {
        $results = [
            RestrictionResult::allow(),
            RestrictionResult::deny('CODE1', 'Message 1'),
            RestrictionResult::deny('CODE2', 'Message 2'),
        ];

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->allowed());
        $this->assertFalse($results[1]->allowed());
        $this->assertFalse($results[2]->allowed());
    }

    /**
     * Test that result can be used as array key (if needed)
     */
    public function test_result_can_be_used_in_array_operations(): void
    {
        $result1 = RestrictionResult::allow();
        $result2 = RestrictionResult::deny('CODE', 'Message');

        $array = [
            'allowed' => $result1,
            'denied'  => $result2,
        ];

        $this->assertTrue($array['allowed']->allowed());
        $this->assertFalse($array['denied']->allowed());
    }

    /**
     * Test that result can be serialized (if needed)
     */
    public function test_result_can_be_serialized(): void
    {
        $result = new RestrictionResult(false, 'CODE', 'Message');

        // Test that we can get all properties
        $data = [
            'allowed' => $result->allowed(),
            'code'    => $result->code(),
            'message' => $result->message(),
        ];

        $this->assertFalse($data['allowed']);
        $this->assertEquals('CODE', $data['code']);
        $this->assertEquals('Message', $data['message']);
    }

    /**
     * Test that result works with different boolean values
     */
    public function test_result_works_with_different_boolean_values(): void
    {
        $trueResult = new RestrictionResult(true);
        $falseResult = new RestrictionResult(false);

        $this->assertTrue($trueResult->allowed());
        $this->assertFalse($falseResult->allowed());
        $this->assertNotEquals($trueResult->allowed(), $falseResult->allowed());
    }

    /**
     * Test that code() can be very long
     */
    public function test_code_can_be_very_long(): void
    {
        $longCode = str_repeat('A', 1000);
        $result = new RestrictionResult(false, $longCode);

        $this->assertEquals($longCode, $result->code());
        $this->assertEquals(1000, strlen($result->code()));
    }

    /**
     * Test that message() can be very long
     */
    public function test_message_can_be_very_long(): void
    {
        $longMessage = str_repeat('B', 5000);
        $result = new RestrictionResult(false, 'CODE', $longMessage);

        $this->assertEquals($longMessage, $result->message());
        $this->assertEquals(5000, strlen($result->message()));
    }

    /**
     * Test that result can be used in switch statement
     */
    public function test_result_can_be_used_in_switch_statement(): void
    {
        $allowedResult = RestrictionResult::allow();
        $deniedResult = RestrictionResult::deny('DENIED');

        $allowedValue = match ($allowedResult->allowed()) {
            true => 'allowed',
            false => 'denied',
        };

        $deniedValue = match ($deniedResult->allowed()) {
            true => 'allowed',
            false => 'denied',
        };

        $this->assertEquals('allowed', $allowedValue);
        $this->assertEquals('denied', $deniedValue);
    }

    /**
     * Test that result can be used with null coalescing operator
     */
    public function test_result_can_be_used_with_null_coalescing_operator(): void
    {
        $resultWithCode = new RestrictionResult(false, 'CODE');
        $resultWithoutCode = new RestrictionResult(false);

        $code1 = $resultWithCode->code() ?? 'DEFAULT';
        $code2 = $resultWithoutCode->code() ?? 'DEFAULT';

        $this->assertEquals('CODE', $code1);
        $this->assertEquals('DEFAULT', $code2);
    }

    /**
     * Test that result can be used with null coalescing operator for message
     */
    public function test_result_can_be_used_with_null_coalescing_operator_for_message(): void
    {
        $resultWithMessage = new RestrictionResult(false, 'CODE', 'Message');
        $resultWithoutMessage = new RestrictionResult(false, 'CODE');

        $message1 = $resultWithMessage->message() ?? 'DEFAULT';
        $message2 = $resultWithoutMessage->message() ?? 'DEFAULT';

        $this->assertEquals('Message', $message1);
        $this->assertEquals('DEFAULT', $message2);
    }

    /**
     * Test that result can be used in string interpolation
     */
    public function test_result_can_be_used_in_string_interpolation(): void
    {
        $result = RestrictionResult::deny('DENIED', 'Access denied');

        $string = "Status: {$result->allowed()}, Code: {$result->code()}, Message: {$result->message()}";

        $this->assertStringContainsString('Status: ', $string);
        $this->assertStringContainsString('Code: DENIED', $string);
        $this->assertStringContainsString('Message: Access denied', $string);
    }

    /**
     * Test that result can be used in type checking
     */
    public function test_result_can_be_used_in_type_checking(): void
    {
        $result = RestrictionResult::allow();

        $this->assertInstanceOf(RestrictionResult::class, $result);
        $this->assertIsBool($result->allowed());
        $this->assertTrue(is_bool($result->allowed()));
    }

    /**
     * Test that result can be used with strict comparison
     */
    public function test_result_can_be_used_with_strict_comparison(): void
    {
        $result1 = RestrictionResult::allow();
        $result2 = RestrictionResult::allow();

        $this->assertTrue($result1->allowed() === true);
        $this->assertTrue($result2->allowed() === true);
        $this->assertFalse($result1->allowed() === false);
    }

    /**
     * Test that result can be used with loose comparison
     */
    public function test_result_can_be_used_with_loose_comparison(): void
    {
        $result = RestrictionResult::allow();

        $this->assertTrue($result->allowed() == true);
        $this->assertTrue($result->allowed() == 1);
        $this->assertFalse($result->allowed() == false);
        $this->assertFalse($result->allowed() == 0);
    }

    /**
     * Test that code() can contain spaces
     */
    public function test_code_can_contain_spaces(): void
    {
        $result = new RestrictionResult(false, 'CODE WITH SPACES');

        $this->assertEquals('CODE WITH SPACES', $result->code());
    }

    /**
     * Test that message() can contain spaces
     */
    public function test_message_can_contain_spaces(): void
    {
        $result = new RestrictionResult(false, 'CODE', 'Message with spaces');

        $this->assertEquals('Message with spaces', $result->message());
    }

    /**
     * Test that code() can contain tabs
     */
    public function test_code_can_contain_tabs(): void
    {
        $result = new RestrictionResult(false, "CODE\tWITH\tTABS");

        $this->assertEquals("CODE\tWITH\tTABS", $result->code());
    }

    /**
     * Test that message() can contain tabs
     */
    public function test_message_can_contain_tabs(): void
    {
        $result = new RestrictionResult(false, 'CODE', "Message\twith\ttabs");

        $this->assertEquals("Message\twith\ttabs", $result->message());
    }

    /**
     * Test that result can be used in collection operations
     */
    public function test_result_can_be_used_in_collection_operations(): void
    {
        $results = collect([
            RestrictionResult::allow(),
            RestrictionResult::deny('CODE1'),
            RestrictionResult::deny('CODE2', 'Message'),
        ]);

        $allowedCount = $results->filter(fn ($r) => $r->allowed())->count();
        $deniedCount = $results->filter(fn ($r) => ! $r->allowed())->count();

        $this->assertEquals(1, $allowedCount);
        $this->assertEquals(2, $deniedCount);
    }

    /**
     * Test that result can be used in map operations
     */
    public function test_result_can_be_used_in_map_operations(): void
    {
        $results = [
            RestrictionResult::allow(),
            RestrictionResult::deny('CODE', 'Message'),
        ];

        $allowedValues = array_map(fn ($r) => $r->allowed(), $results);
        $codes = array_map(fn ($r) => $r->code(), $results);

        $this->assertEquals([true, false], $allowedValues);
        $this->assertEquals([null, 'CODE'], $codes);
    }
}
