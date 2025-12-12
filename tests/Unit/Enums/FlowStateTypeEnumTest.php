<?php

namespace JobMetric\Flow\Tests\Unit\Enums;

use JobMetric\Flow\Enums\FlowStateTypeEnum;
use JobMetric\Flow\Tests\TestCase;
use ReflectionClass;

/**
 * Comprehensive tests for FlowStateTypeEnum
 *
 * This enum represents the type of flow states in the workflow system.
 * It defines two possible types: START (initial state) and STATE (regular state).
 * These tests ensure the enum correctly defines its cases, values, and behavior.
 */
class FlowStateTypeEnumTest extends TestCase
{
    /**
     * Test that START case exists and has correct value
     */
    public function test_start_case_exists_and_has_correct_value(): void
    {
        $start = FlowStateTypeEnum::START;

        $this->assertInstanceOf(FlowStateTypeEnum::class, $start);
        $this->assertEquals('start', $start->value);
        $this->assertSame('start', $start->value);
    }

    /**
     * Test that STATE case exists and has correct value
     */
    public function test_state_case_exists_and_has_correct_value(): void
    {
        $state = FlowStateTypeEnum::STATE;

        $this->assertInstanceOf(FlowStateTypeEnum::class, $state);
        $this->assertEquals('state', $state->value);
        $this->assertSame('state', $state->value);
    }

    /**
     * Test that enum has exactly two cases
     */
    public function test_enum_has_exactly_two_cases(): void
    {
        $cases = FlowStateTypeEnum::cases();

        $this->assertCount(2, $cases);
        $this->assertContains(FlowStateTypeEnum::START, $cases);
        $this->assertContains(FlowStateTypeEnum::STATE, $cases);
    }

    /**
     * Test that enum cases have unique values
     */
    public function test_enum_cases_have_unique_values(): void
    {
        $cases = FlowStateTypeEnum::cases();
        $values = array_map(fn ($case) => $case->value, $cases);

        $this->assertCount(2, $values);
        $this->assertCount(2, array_unique($values));
        $this->assertNotEquals(FlowStateTypeEnum::START->value, FlowStateTypeEnum::STATE->value);
    }

    /**
     * Test that enum can be created from value
     */
    public function test_enum_can_be_created_from_value(): void
    {
        $start = FlowStateTypeEnum::from('start');
        $state = FlowStateTypeEnum::from('state');

        $this->assertSame(FlowStateTypeEnum::START, $start);
        $this->assertSame(FlowStateTypeEnum::STATE, $state);
    }

    /**
     * Test that enum throws exception for invalid value in from()
     */
    public function test_enum_throws_exception_for_invalid_value_in_from(): void
    {
        $this->expectException(\ValueError::class);

        FlowStateTypeEnum::from('invalid');
    }

    /**
     * Test that enum can be created from value using tryFrom()
     */
    public function test_enum_can_be_created_from_value_using_try_from(): void
    {
        $start = FlowStateTypeEnum::tryFrom('start');
        $state = FlowStateTypeEnum::tryFrom('state');

        $this->assertSame(FlowStateTypeEnum::START, $start);
        $this->assertSame(FlowStateTypeEnum::STATE, $state);
    }

    /**
     * Test that tryFrom() returns null for invalid value
     */
    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $result = FlowStateTypeEnum::tryFrom('invalid');

        $this->assertNull($result);
    }

    /**
     * Test that enum cases can be compared
     */
    public function test_enum_cases_can_be_compared(): void
    {
        $start1 = FlowStateTypeEnum::START;
        $start2 = FlowStateTypeEnum::START;
        $state = FlowStateTypeEnum::STATE;

        $this->assertSame($start1, $start2);
        $this->assertNotSame($start1, $state);
        $this->assertTrue($start1 === $start2);
        $this->assertFalse($start1 === $state);
    }

    /**
     * Test that enum can be serialized to string via value property
     */
    public function test_enum_can_be_serialized_to_string(): void
    {
        $start = FlowStateTypeEnum::START;
        $state = FlowStateTypeEnum::STATE;

        $this->assertEquals('start', $start->value);
        $this->assertEquals('state', $state->value);
        $this->assertIsString($start->value);
        $this->assertIsString($state->value);
    }

    /**
     * Test that enum can be serialized to JSON
     */
    public function test_enum_can_be_serialized_to_json(): void
    {
        $start = FlowStateTypeEnum::START;
        $state = FlowStateTypeEnum::STATE;

        $this->assertEquals('"start"', json_encode($start));
        $this->assertEquals('"state"', json_encode($state));
    }

    /**
     * Test that enum can be deserialized from JSON
     */
    public function test_enum_can_be_deserialized_from_json(): void
    {
        $startJson = '"start"';
        $stateJson = '"state"';

        $start = FlowStateTypeEnum::from(json_decode($startJson));
        $state = FlowStateTypeEnum::from(json_decode($stateJson));

        $this->assertSame(FlowStateTypeEnum::START, $start);
        $this->assertSame(FlowStateTypeEnum::STATE, $state);
    }

    /**
     * Test that enum name property returns case name
     */
    public function test_enum_name_property_returns_case_name(): void
    {
        $start = FlowStateTypeEnum::START;
        $state = FlowStateTypeEnum::STATE;

        $this->assertEquals('START', $start->name);
        $this->assertEquals('STATE', $state->name);
    }

    /**
     * Test that enum can be used in switch statements
     */
    public function test_enum_can_be_used_in_switch_statements(): void
    {
        $start = FlowStateTypeEnum::START;
        $state = FlowStateTypeEnum::STATE;

        $startResult = match ($start) {
            FlowStateTypeEnum::START => 'is_start',
            FlowStateTypeEnum::STATE => 'is_state',
        };

        $stateResult = match ($state) {
            FlowStateTypeEnum::START => 'is_start',
            FlowStateTypeEnum::STATE => 'is_state',
        };

        $this->assertEquals('is_start', $startResult);
        $this->assertEquals('is_state', $stateResult);
    }

    /**
     * Test that enum values can be used in array keys
     */
    public function test_enum_can_be_used_in_array_keys(): void
    {
        $array = [
            FlowStateTypeEnum::START->value => 'Initial state',
            FlowStateTypeEnum::STATE->value => 'Regular state',
        ];

        $this->assertEquals('Initial state', $array[FlowStateTypeEnum::START->value]);
        $this->assertEquals('Regular state', $array[FlowStateTypeEnum::STATE->value]);
        $this->assertCount(2, $array);
        $this->assertArrayHasKey('start', $array);
        $this->assertArrayHasKey('state', $array);
    }

    /**
     * Test that enum can be used in type hints
     */
    public function test_enum_can_be_used_in_type_hints(): void
    {
        $this->assertTrue($this->acceptEnumType(FlowStateTypeEnum::START));
        $this->assertTrue($this->acceptEnumType(FlowStateTypeEnum::STATE));
    }

    /**
     * Helper method to test type hints
     */
    private function acceptEnumType(FlowStateTypeEnum $enum): bool
    {
        return $enum instanceof FlowStateTypeEnum;
    }

    /**
     * Test that enum values are lowercase strings
     */
    public function test_enum_values_are_lowercase_strings(): void
    {
        $cases = FlowStateTypeEnum::cases();

        foreach ($cases as $case) {
            $this->assertIsString($case->value);
            $this->assertEquals(strtolower($case->value), $case->value);
        }
    }

    /**
     * Test that enum can be used in database queries
     */
    public function test_enum_can_be_used_in_database_queries(): void
    {
        $startValue = FlowStateTypeEnum::START->value;
        $stateValue = FlowStateTypeEnum::STATE->value;

        $this->assertEquals('start', $startValue);
        $this->assertEquals('state', $stateValue);
        $this->assertIsString($startValue);
        $this->assertIsString($stateValue);
    }

    /**
     * Test that enum cases are instances of the enum class
     */
    public function test_enum_cases_are_instances_of_enum_class(): void
    {
        $start = FlowStateTypeEnum::START;
        $state = FlowStateTypeEnum::STATE;

        $this->assertInstanceOf(FlowStateTypeEnum::class, $start);
        $this->assertInstanceOf(FlowStateTypeEnum::class, $state);
    }

    /**
     * Test that enum can be used in array_map
     */
    public function test_enum_can_be_used_in_array_map(): void
    {
        $cases = FlowStateTypeEnum::cases();
        $values = array_map(fn ($case) => $case->value, $cases);

        $this->assertContains('start', $values);
        $this->assertContains('state', $values);
        $this->assertCount(2, $values);
    }

    /**
     * Test that enum can be used in array_filter
     */
    public function test_enum_can_be_used_in_array_filter(): void
    {
        $cases = FlowStateTypeEnum::cases();
        $startCases = array_filter($cases, fn ($case) => $case === FlowStateTypeEnum::START);

        $this->assertCount(1, $startCases);
        $this->assertContains(FlowStateTypeEnum::START, $startCases);
    }

    /**
     * Test that enum can be used in in_array
     */
    public function test_enum_can_be_used_in_in_array(): void
    {
        $cases = FlowStateTypeEnum::cases();

        $this->assertTrue(in_array(FlowStateTypeEnum::START, $cases, true));
        $this->assertTrue(in_array(FlowStateTypeEnum::STATE, $cases, true));
    }

    /**
     * Test that enum can be used in match expression with value
     */
    public function test_enum_can_be_used_in_match_expression_with_value(): void
    {
        $start = FlowStateTypeEnum::START;
        $state = FlowStateTypeEnum::STATE;

        $startResult = match ($start->value) {
            'start' => 'matched_start',
            'state' => 'matched_state',
            default => 'no_match',
        };

        $stateResult = match ($state->value) {
            'start' => 'matched_start',
            'state' => 'matched_state',
            default => 'no_match',
        };

        $this->assertEquals('matched_start', $startResult);
        $this->assertEquals('matched_state', $stateResult);
    }

    /**
     * Test that enum implements backed enum interface
     */
    public function test_enum_implements_backed_enum_interface(): void
    {
        $reflection = new \ReflectionEnum(FlowStateTypeEnum::class);

        $this->assertTrue($reflection->isBacked());
        $this->assertEquals('string', $reflection->getBackingType()->getName());
    }

    /**
     * Test that enum has EnumMacros trait
     */
    public function test_enum_has_enum_macros_trait(): void
    {
        $reflection = new ReflectionClass(FlowStateTypeEnum::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('JobMetric\PackageCore\Enums\EnumMacros', $traits);
    }

    /**
     * Test that enum can be used in strict comparisons
     */
    public function test_enum_can_be_used_in_strict_comparisons(): void
    {
        $start1 = FlowStateTypeEnum::from('start');
        $start2 = FlowStateTypeEnum::START;

        $this->assertTrue($start1 === $start2);
        $this->assertFalse($start1 !== $start2);
    }

    /**
     * Test that enum values match their case names in lowercase
     */
    public function test_enum_values_match_case_names_in_lowercase(): void
    {
        $start = FlowStateTypeEnum::START;
        $state = FlowStateTypeEnum::STATE;

        $this->assertEquals(strtolower($start->name), $start->value);
        $this->assertEquals(strtolower($state->name), $state->value);
    }
}
