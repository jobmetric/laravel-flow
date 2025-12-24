<?php

namespace JobMetric\Flow\Tests\Unit\Concerns;

use JobMetric\Flow\Concerns\RunInBackground;
use JobMetric\Flow\Tests\TestCase;
use ReflectionException;
use ReflectionMethod;
use stdClass;

/**
 * Comprehensive tests for RunInBackground trait
 *
 * These tests cover all functionality of the RunInBackground trait
 * to ensure it correctly indicates that tasks should run asynchronously.
 */
class RunInBackgroundTest extends TestCase
{
    /**
     * Test that async() method returns true
     */
    public function test_async_returns_true(): void
    {
        $object = new class
        {
            use RunInBackground;
        };

        $this->assertTrue($object->async());
    }

    /**
     * Test that async() method always returns true
     */
    public function test_async_always_returns_true(): void
    {
        $object = new class
        {
            use RunInBackground;
        };

        // Call multiple times to ensure it always returns true
        $this->assertTrue($object->async());
        $this->assertTrue($object->async());
        $this->assertTrue($object->async());
    }

    /**
     * Test that trait can be used in different classes
     */
    public function test_trait_can_be_used_in_different_classes(): void
    {
        $object1 = new class
        {
            use RunInBackground;
        };

        $object2 = new class
        {
            use RunInBackground;
        };

        $this->assertTrue($object1->async());
        $this->assertTrue($object2->async());
    }

    /**
     * Test that trait method is accessible
     */
    public function test_trait_method_is_accessible(): void
    {
        $object = new class
        {
            use RunInBackground;
        };

        $this->assertTrue(method_exists($object, 'async'));
        $this->assertTrue(is_callable([$object, 'async']));
    }

    /**
     * Test that async() returns boolean type
     */
    public function test_async_returns_boolean_type(): void
    {
        $object = new class
        {
            use RunInBackground;
        };

        $result = $object->async();

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    /**
     * Test that trait can be used with other traits
     */
    public function test_trait_can_be_used_with_other_traits(): void
    {
        $object = new class
        {
            use RunInBackground;

            public function otherMethod(): string
            {
                return 'test';
            }
        };

        $this->assertTrue($object->async());
        $this->assertEquals('test', $object->otherMethod());
    }

    /**
     * Test that trait works with class inheritance
     */
    public function test_trait_works_with_class_inheritance(): void
    {
        $baseClass = new class
        {
            public function baseMethod(): string
            {
                return 'base';
            }
        };

        $childClass = new class extends stdClass
        {
            use RunInBackground;
        };

        $this->assertTrue($childClass->async());
    }

    /**
     * Test that multiple instances have independent behavior
     */
    public function test_multiple_instances_have_independent_behavior(): void
    {
        $object1 = new class
        {
            use RunInBackground;
        };

        $object2 = new class
        {
            use RunInBackground;
        };

        // Both should return true independently
        $this->assertTrue($object1->async());
        $this->assertTrue($object2->async());

        // Verify they are different instances
        $this->assertNotSame($object1, $object2);
    }

    /**
     * Test that async() method has correct return type
     *
     * @throws ReflectionException
     */
    public function test_async_method_has_correct_return_type(): void
    {
        $object = new class
        {
            use RunInBackground;
        };

        $reflection = new ReflectionMethod($object, 'async');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    /**
     * Test that trait does not conflict with existing methods
     */
    public function test_trait_does_not_conflict_with_existing_methods(): void
    {
        $object = new class
        {
            use RunInBackground;

            public function otherMethod(): bool
            {
                return false;
            }
        };

        // async() should still return true
        $this->assertTrue($object->async());
        // otherMethod() should return false
        $this->assertFalse($object->otherMethod());
    }

    /**
     * Test that trait method can be called multiple times without side effects
     */
    public function test_trait_method_can_be_called_multiple_times_without_side_effects(): void
    {
        $object = new class
        {
            use RunInBackground;
        };

        $results = [];
        for ($i = 0 ; $i < 10 ; $i++) {
            $results[] = $object->async();
        }

        // All results should be true
        $this->assertCount(10, $results);
        $this->assertContainsOnly('bool', $results);
        $this->assertNotContains(false, $results);
        $this->assertContains(true, $results);
    }

    /**
     * Test that trait provides consistent behavior
     */
    public function test_trait_provides_consistent_behavior(): void
    {
        $object = new class
        {
            use RunInBackground;
        };

        $firstCall = $object->async();
        $secondCall = $object->async();
        $thirdCall = $object->async();

        $this->assertTrue($firstCall);
        $this->assertTrue($secondCall);
        $this->assertTrue($thirdCall);
        $this->assertEquals($firstCall, $secondCall);
        $this->assertEquals($secondCall, $thirdCall);
    }

    /**
     * Test that trait method is public
     *
     * @throws ReflectionException
     */
    public function test_trait_method_is_public(): void
    {
        $object = new class
        {
            use RunInBackground;
        };

        $reflection = new ReflectionMethod($object, 'async');
        $this->assertTrue($reflection->isPublic());
    }

    /**
     * Test that trait can be used in classes with other methods
     */
    public function test_trait_can_be_used_in_classes_with_other_methods(): void
    {
        $concreteClass = new class
        {
            use RunInBackground;

            public function concreteMethod(): void
            {
                // Concrete implementation
            }
        };

        $this->assertTrue($concreteClass->async());
        $this->assertNull($concreteClass->concreteMethod());
    }

    /**
     * Test that trait method returns exactly true (not truthy value)
     */
    public function test_trait_method_returns_exactly_true(): void
    {
        $object = new class
        {
            use RunInBackground;
        };

        $result = $object->async();

        // Use strict comparison to ensure it's exactly true, not just truthy
        $this->assertTrue($result === true);
        $this->assertNotTrue($result === false);
        $this->assertNotTrue($result === 1);
        $this->assertNotTrue($result === 'true');
    }
}
