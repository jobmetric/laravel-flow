<?php

namespace JobMetric\Flow\Tests\Unit\DTO;

use JobMetric\Flow\DTO\TransitionResult;
use JobMetric\Flow\Tests\TestCase;

/**
 * Comprehensive tests for TransitionResult
 *
 * These tests cover all functionality of the TransitionResult DTO class
 * to ensure it correctly handles success/failure states, messages, errors, data, and metadata.
 */
class TransitionResultTest extends TestCase
{
    /**
     * Test that constructor creates instance with default values
     */
    public function test_constructor_creates_instance_with_default_values(): void
    {
        $result = new TransitionResult();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ok', $result->getStatus());
        $this->assertNull($result->getCode());
        $this->assertEmpty($result->getMessages());
        $this->assertEmpty($result->getErrors());
        $this->assertEmpty($result->getData());
        $this->assertEmpty($result->getMeta());
    }

    /**
     * Test that constructor accepts success parameter
     */
    public function test_constructor_accepts_success_parameter(): void
    {
        $result = new TransitionResult(false);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('ok', $result->getStatus());
    }

    /**
     * Test that constructor accepts status parameter
     */
    public function test_constructor_accepts_status_parameter(): void
    {
        $result = new TransitionResult(true, 'custom_status');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('custom_status', $result->getStatus());
    }

    /**
     * Test that constructor accepts code parameter
     */
    public function test_constructor_accepts_code_parameter(): void
    {
        $result = new TransitionResult(true, 'ok', 'CUSTOM_CODE');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('CUSTOM_CODE', $result->getCode());
    }

    /**
     * Test that success() static method creates successful result
     */
    public function test_success_static_method_creates_successful_result(): void
    {
        $result = TransitionResult::success();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ok', $result->getStatus());
        $this->assertEmpty($result->getData());
    }

    /**
     * Test that success() static method accepts initial data
     */
    public function test_success_static_method_accepts_initial_data(): void
    {
        $data = ['key' => 'value'];
        $result = TransitionResult::success($data);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($data, $result->getData());
    }

    /**
     * Test that failure() static method creates failed result
     */
    public function test_failure_static_method_creates_failed_result(): void
    {
        $result = TransitionResult::failure();

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('failed', $result->getStatus());
        $this->assertEmpty($result->getErrors());
    }

    /**
     * Test that failure() static method accepts error message
     */
    public function test_failure_static_method_accepts_error_message(): void
    {
        $message = 'Something went wrong';
        $result = TransitionResult::failure($message);

        $this->assertFalse($result->isSuccess());
        $this->assertContains($message, $result->getErrors());
    }

    /**
     * Test that failure() static method accepts code
     */
    public function test_failure_static_method_accepts_code(): void
    {
        $code = 'ERROR_CODE';
        $result = TransitionResult::failure(null, $code);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals($code, $result->getCode());
    }

    /**
     * Test that failure() static method accepts both message and code
     */
    public function test_failure_static_method_accepts_both_message_and_code(): void
    {
        $message = 'Error occurred';
        $code = 'ERROR_CODE';
        $result = TransitionResult::failure($message, $code);

        $this->assertFalse($result->isSuccess());
        $this->assertContains($message, $result->getErrors());
        $this->assertEquals($code, $result->getCode());
    }

    /**
     * Test that markSuccess() marks result as successful
     */
    public function test_mark_success_marks_result_as_successful(): void
    {
        $result = new TransitionResult(false);
        $result->markSuccess();

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ok', $result->getStatus());
    }

    /**
     * Test that markSuccess() accepts custom status
     */
    public function test_mark_success_accepts_custom_status(): void
    {
        $result = new TransitionResult(false);
        $result->markSuccess('custom_ok');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('custom_ok', $result->getStatus());
    }

    /**
     * Test that markSuccess() returns self for chaining
     */
    public function test_mark_success_returns_self_for_chaining(): void
    {
        $result = new TransitionResult(false);
        $returned = $result->markSuccess();

        $this->assertSame($result, $returned);
    }

    /**
     * Test that markFailed() marks result as failed
     */
    public function test_mark_failed_marks_result_as_failed(): void
    {
        $result = new TransitionResult(true);
        $result->markFailed();

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('failed', $result->getStatus());
    }

    /**
     * Test that markFailed() accepts custom status
     */
    public function test_mark_failed_accepts_custom_status(): void
    {
        $result = new TransitionResult(true);
        $result->markFailed('error_status');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('error_status', $result->getStatus());
    }

    /**
     * Test that markFailed() accepts code
     */
    public function test_mark_failed_accepts_code(): void
    {
        $result = new TransitionResult(true);
        $result->markFailed('failed', 'ERROR_CODE');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('ERROR_CODE', $result->getCode());
    }

    /**
     * Test that markFailed() returns self for chaining
     */
    public function test_mark_failed_returns_self_for_chaining(): void
    {
        $result = new TransitionResult(true);
        $returned = $result->markFailed();

        $this->assertSame($result, $returned);
    }

    /**
     * Test that addMessage() adds informational message
     */
    public function test_add_message_adds_informational_message(): void
    {
        $result = new TransitionResult();
        $result->addMessage('Info message');

        $this->assertContains('Info message', $result->getMessages());
        $this->assertCount(1, $result->getMessages());
    }

    /**
     * Test that addMessage() can be called multiple times
     */
    public function test_add_message_can_be_called_multiple_times(): void
    {
        $result = new TransitionResult();
        $result->addMessage('Message 1');
        $result->addMessage('Message 2');
        $result->addMessage('Message 3');

        $this->assertCount(3, $result->getMessages());
        $this->assertContains('Message 1', $result->getMessages());
        $this->assertContains('Message 2', $result->getMessages());
        $this->assertContains('Message 3', $result->getMessages());
    }

    /**
     * Test that addMessage() returns self for chaining
     */
    public function test_add_message_returns_self_for_chaining(): void
    {
        $result = new TransitionResult();
        $returned = $result->addMessage('Message');

        $this->assertSame($result, $returned);
    }

    /**
     * Test that addError() adds error message
     */
    public function test_add_error_adds_error_message(): void
    {
        $result = new TransitionResult();
        $result->addError('Error message');

        $this->assertContains('Error message', $result->getErrors());
        $this->assertCount(1, $result->getErrors());
    }

    /**
     * Test that addError() marks result as failed by default
     */
    public function test_add_error_marks_result_as_failed_by_default(): void
    {
        $result = new TransitionResult(true);
        $result->addError('Error message');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('failed', $result->getStatus());
    }

    /**
     * Test that addError() can add error without marking as failed
     */
    public function test_add_error_can_add_error_without_marking_as_failed(): void
    {
        $result = new TransitionResult(true);
        $result->addError('Error message', false);

        $this->assertTrue($result->isSuccess());
        $this->assertContains('Error message', $result->getErrors());
    }

    /**
     * Test that addError() can be called multiple times
     */
    public function test_add_error_can_be_called_multiple_times(): void
    {
        $result = new TransitionResult();
        $result->addError('Error 1');
        $result->addError('Error 2');
        $result->addError('Error 3');

        $this->assertCount(3, $result->getErrors());
        $this->assertContains('Error 1', $result->getErrors());
        $this->assertContains('Error 2', $result->getErrors());
        $this->assertContains('Error 3', $result->getErrors());
    }

    /**
     * Test that addError() returns self for chaining
     */
    public function test_add_error_returns_self_for_chaining(): void
    {
        $result = new TransitionResult();
        $returned = $result->addError('Error');

        $this->assertSame($result, $returned);
    }

    /**
     * Test that mergeData() merges data into existing data
     */
    public function test_merge_data_merges_data_into_existing_data(): void
    {
        $result = new TransitionResult();
        $result->setData('key1', 'value1');
        $result->mergeData(['key2' => 'value2', 'key3' => 'value3']);

        $data = $result->getData();
        $this->assertEquals('value1', $data['key1']);
        $this->assertEquals('value2', $data['key2']);
        $this->assertEquals('value3', $data['key3']);
    }

    /**
     * Test that mergeData() overwrites existing keys
     */
    public function test_merge_data_overwrites_existing_keys(): void
    {
        $result = new TransitionResult();
        $result->setData('key', 'old_value');
        $result->mergeData(['key' => 'new_value']);

        $this->assertEquals('new_value', $result->getData()['key']);
    }

    /**
     * Test that mergeData() returns self for chaining
     */
    public function test_merge_data_returns_self_for_chaining(): void
    {
        $result = new TransitionResult();
        $returned = $result->mergeData(['key' => 'value']);

        $this->assertSame($result, $returned);
    }

    /**
     * Test that setData() sets single data key
     */
    public function test_set_data_sets_single_data_key(): void
    {
        $result = new TransitionResult();
        $result->setData('key', 'value');

        $this->assertEquals('value', $result->getData()['key']);
    }

    /**
     * Test that setData() can set multiple keys
     */
    public function test_set_data_can_set_multiple_keys(): void
    {
        $result = new TransitionResult();
        $result->setData('key1', 'value1');
        $result->setData('key2', 'value2');
        $result->setData('key3', 'value3');

        $data = $result->getData();
        $this->assertEquals('value1', $data['key1']);
        $this->assertEquals('value2', $data['key2']);
        $this->assertEquals('value3', $data['key3']);
    }

    /**
     * Test that setData() overwrites existing key
     */
    public function test_set_data_overwrites_existing_key(): void
    {
        $result = new TransitionResult();
        $result->setData('key', 'old_value');
        $result->setData('key', 'new_value');

        $this->assertEquals('new_value', $result->getData()['key']);
    }

    /**
     * Test that setData() returns self for chaining
     */
    public function test_set_data_returns_self_for_chaining(): void
    {
        $result = new TransitionResult();
        $returned = $result->setData('key', 'value');

        $this->assertSame($result, $returned);
    }

    /**
     * Test that mergeMeta() merges metadata into existing metadata
     */
    public function test_merge_meta_merges_metadata_into_existing_metadata(): void
    {
        $result = new TransitionResult();
        $result->setMeta('key1', 'value1');
        $result->mergeMeta(['key2' => 'value2', 'key3' => 'value3']);

        $meta = $result->getMeta();
        $this->assertEquals('value1', $meta['key1']);
        $this->assertEquals('value2', $meta['key2']);
        $this->assertEquals('value3', $meta['key3']);
    }

    /**
     * Test that mergeMeta() overwrites existing keys
     */
    public function test_merge_meta_overwrites_existing_keys(): void
    {
        $result = new TransitionResult();
        $result->setMeta('key', 'old_value');
        $result->mergeMeta(['key' => 'new_value']);

        $this->assertEquals('new_value', $result->getMeta()['key']);
    }

    /**
     * Test that mergeMeta() returns self for chaining
     */
    public function test_merge_meta_returns_self_for_chaining(): void
    {
        $result = new TransitionResult();
        $returned = $result->mergeMeta(['key' => 'value']);

        $this->assertSame($result, $returned);
    }

    /**
     * Test that setMeta() sets single metadata key
     */
    public function test_set_meta_sets_single_metadata_key(): void
    {
        $result = new TransitionResult();
        $result->setMeta('key', 'value');

        $this->assertEquals('value', $result->getMeta()['key']);
    }

    /**
     * Test that setMeta() can set multiple keys
     */
    public function test_set_meta_can_set_multiple_keys(): void
    {
        $result = new TransitionResult();
        $result->setMeta('key1', 'value1');
        $result->setMeta('key2', 'value2');
        $result->setMeta('key3', 'value3');

        $meta = $result->getMeta();
        $this->assertEquals('value1', $meta['key1']);
        $this->assertEquals('value2', $meta['key2']);
        $this->assertEquals('value3', $meta['key3']);
    }

    /**
     * Test that setMeta() overwrites existing key
     */
    public function test_set_meta_overwrites_existing_key(): void
    {
        $result = new TransitionResult();
        $result->setMeta('key', 'old_value');
        $result->setMeta('key', 'new_value');

        $this->assertEquals('new_value', $result->getMeta()['key']);
    }

    /**
     * Test that setMeta() returns self for chaining
     */
    public function test_set_meta_returns_self_for_chaining(): void
    {
        $result = new TransitionResult();
        $returned = $result->setMeta('key', 'value');

        $this->assertSame($result, $returned);
    }

    /**
     * Test that merge() merges another TransitionResult
     */
    public function test_merge_merges_another_transition_result(): void
    {
        $result1 = new TransitionResult();
        $result1->addMessage('Message 1');
        $result1->setData('key1', 'value1');

        $result2 = new TransitionResult();
        $result2->addMessage('Message 2');
        $result2->setData('key2', 'value2');

        $result1->merge($result2);

        $this->assertContains('Message 1', $result1->getMessages());
        $this->assertContains('Message 2', $result1->getMessages());
        $this->assertEquals('value1', $result1->getData()['key1']);
        $this->assertEquals('value2', $result1->getData()['key2']);
    }

    /**
     * Test that merge() merges errors from other result
     */
    public function test_merge_merges_errors_from_other_result(): void
    {
        $result1 = new TransitionResult();
        $result2 = new TransitionResult();
        $result2->addError('Error from result2');

        $result1->merge($result2);

        $this->assertContains('Error from result2', $result1->getErrors());
    }

    /**
     * Test that merge() merges metadata from other result
     */
    public function test_merge_merges_metadata_from_other_result(): void
    {
        $result1 = new TransitionResult();
        $result2 = new TransitionResult();
        $result2->setMeta('meta_key', 'meta_value');

        $result1->merge($result2);

        $this->assertEquals('meta_value', $result1->getMeta()['meta_key']);
    }

    /**
     * Test that merge() marks result as failed if other result is failed
     */
    public function test_merge_marks_result_as_failed_if_other_result_is_failed(): void
    {
        $result1 = new TransitionResult(true);
        $result2 = new TransitionResult(false);
        $result2->markFailed('error_status', 'ERROR_CODE');

        $result1->merge($result2);

        $this->assertFalse($result1->isSuccess());
        $this->assertEquals('error_status', $result1->getStatus());
        $this->assertEquals('ERROR_CODE', $result1->getCode());
    }

    /**
     * Test that merge() keeps success if other result is successful
     */
    public function test_merge_keeps_success_if_other_result_is_successful(): void
    {
        $result1 = new TransitionResult(true);
        $result2 = new TransitionResult(true);

        $result1->merge($result2);

        $this->assertTrue($result1->isSuccess());
    }

    /**
     * Test that merge() returns self for chaining
     */
    public function test_merge_returns_self_for_chaining(): void
    {
        $result1 = new TransitionResult();
        $result2 = new TransitionResult();
        $returned = $result1->merge($result2);

        $this->assertSame($result1, $returned);
    }

    /**
     * Test that isSuccess() returns true for successful result
     */
    public function test_is_success_returns_true_for_successful_result(): void
    {
        $result = new TransitionResult(true);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * Test that isSuccess() returns false for failed result
     */
    public function test_is_success_returns_false_for_failed_result(): void
    {
        $result = new TransitionResult(false);

        $this->assertFalse($result->isSuccess());
    }

    /**
     * Test that hasErrors() returns false when no errors
     */
    public function test_has_errors_returns_false_when_no_errors(): void
    {
        $result = new TransitionResult();

        $this->assertFalse($result->hasErrors());
    }

    /**
     * Test that hasErrors() returns true when errors exist
     */
    public function test_has_errors_returns_true_when_errors_exist(): void
    {
        $result = new TransitionResult();
        $result->addError('Error message');

        $this->assertTrue($result->hasErrors());
    }

    /**
     * Test that getStatus() returns current status
     */
    public function test_get_status_returns_current_status(): void
    {
        $result = new TransitionResult(true, 'custom_status');

        $this->assertEquals('custom_status', $result->getStatus());
    }

    /**
     * Test that getCode() returns code when set
     */
    public function test_get_code_returns_code_when_set(): void
    {
        $result = new TransitionResult(true, 'ok', 'CUSTOM_CODE');

        $this->assertEquals('CUSTOM_CODE', $result->getCode());
    }

    /**
     * Test that getCode() returns null when not set
     */
    public function test_get_code_returns_null_when_not_set(): void
    {
        $result = new TransitionResult();

        $this->assertNull($result->getCode());
    }

    /**
     * Test that getMessages() returns all messages
     */
    public function test_get_messages_returns_all_messages(): void
    {
        $result = new TransitionResult();
        $result->addMessage('Message 1');
        $result->addMessage('Message 2');

        $messages = $result->getMessages();
        $this->assertCount(2, $messages);
        $this->assertContains('Message 1', $messages);
        $this->assertContains('Message 2', $messages);
    }

    /**
     * Test that getErrors() returns all errors
     */
    public function test_get_errors_returns_all_errors(): void
    {
        $result = new TransitionResult();
        $result->addError('Error 1');
        $result->addError('Error 2');

        $errors = $result->getErrors();
        $this->assertCount(2, $errors);
        $this->assertContains('Error 1', $errors);
        $this->assertContains('Error 2', $errors);
    }

    /**
     * Test that getData() returns all data
     */
    public function test_get_data_returns_all_data(): void
    {
        $result = new TransitionResult();
        $result->setData('key1', 'value1');
        $result->setData('key2', 'value2');

        $data = $result->getData();
        $this->assertCount(2, $data);
        $this->assertEquals('value1', $data['key1']);
        $this->assertEquals('value2', $data['key2']);
    }

    /**
     * Test that getMeta() returns all metadata
     */
    public function test_get_meta_returns_all_metadata(): void
    {
        $result = new TransitionResult();
        $result->setMeta('key1', 'value1');
        $result->setMeta('key2', 'value2');

        $meta = $result->getMeta();
        $this->assertCount(2, $meta);
        $this->assertEquals('value1', $meta['key1']);
        $this->assertEquals('value2', $meta['key2']);
    }

    /**
     * Test that toArray() returns correct array structure
     */
    public function test_to_array_returns_correct_array_structure(): void
    {
        $result = new TransitionResult(true, 'ok', 'CODE');
        $result->addMessage('Message');
        $result->addError('Error');
        $result->setData('key', 'value');
        $result->setMeta('meta_key', 'meta_value');

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('messages', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('meta', $array);
    }

    /**
     * Test that toArray() returns correct values
     */
    public function test_to_array_returns_correct_values(): void
    {
        $result = new TransitionResult(true, 'ok', 'CODE');
        $result->addMessage('Message');
        $result->addError('Error');
        $result->setData('key', 'value');
        $result->setMeta('meta_key', 'meta_value');

        $array = $result->toArray();

        // addError() marks result as failed by default
        $this->assertFalse($array['success']);
        $this->assertEquals('failed', $array['status']);
        $this->assertEquals('CODE', $array['code']);
        $this->assertContains('Message', $array['messages']);
        $this->assertContains('Error', $array['errors']);
        $this->assertEquals('value', $array['data']['key']);
        $this->assertEquals('meta_value', $array['meta']['meta_key']);
    }

    /**
     * Test that toArray() returns empty arrays for empty collections
     */
    public function test_to_array_returns_empty_arrays_for_empty_collections(): void
    {
        $result = new TransitionResult();

        $array = $result->toArray();

        $this->assertIsArray($array['messages']);
        $this->assertIsArray($array['errors']);
        $this->assertIsArray($array['data']);
        $this->assertIsArray($array['meta']);
        $this->assertEmpty($array['messages']);
        $this->assertEmpty($array['errors']);
        $this->assertEmpty($array['data']);
        $this->assertEmpty($array['meta']);
    }

    /**
     * Test that method chaining works correctly
     */
    public function test_method_chaining_works_correctly(): void
    {
        $result = new TransitionResult();
        $result->addMessage('Message 1')
            ->addMessage('Message 2')
            ->addError('Error 1')
            ->setData('key1', 'value1')
            ->setData('key2', 'value2')
            ->setMeta('meta1', 'value1')
            ->mergeData(['key3' => 'value3'])
            ->mergeMeta(['meta2' => 'value2']);

        $this->assertCount(2, $result->getMessages());
        $this->assertCount(1, $result->getErrors());
        $this->assertCount(3, $result->getData());
        $this->assertCount(2, $result->getMeta());
    }

    /**
     * Test that multiple merges work correctly
     */
    public function test_multiple_merges_work_correctly(): void
    {
        $result1 = new TransitionResult();
        $result1->addMessage('Message 1');

        $result2 = new TransitionResult();
        $result2->addMessage('Message 2');

        $result3 = new TransitionResult();
        $result3->addMessage('Message 3');

        $result1->merge($result2)->merge($result3);

        $this->assertCount(3, $result1->getMessages());
        $this->assertContains('Message 1', $result1->getMessages());
        $this->assertContains('Message 2', $result1->getMessages());
        $this->assertContains('Message 3', $result1->getMessages());
    }

    /**
     * Test that merge() handles complex scenarios
     */
    public function test_merge_handles_complex_scenarios(): void
    {
        $result1 = TransitionResult::success(['key1' => 'value1']);
        $result1->addMessage('Message 1');
        $result1->setMeta('meta1', 'value1');

        $result2 = TransitionResult::failure('Error message', 'ERROR_CODE');
        $result2->addMessage('Message 2');
        $result2->setData('key2', 'value2');
        $result2->setMeta('meta2', 'value2');

        $result1->merge($result2);

        $this->assertFalse($result1->isSuccess());
        $this->assertEquals('failed', $result1->getStatus());
        $this->assertEquals('ERROR_CODE', $result1->getCode());
        $this->assertCount(2, $result1->getMessages());
        $this->assertCount(1, $result1->getErrors());
        $this->assertCount(2, $result1->getData());
        $this->assertCount(2, $result1->getMeta());
    }

    /**
     * Test that setData() accepts various value types
     */
    public function test_set_data_accepts_various_value_types(): void
    {
        $result = new TransitionResult();
        $result->setData('string', 'value');
        $result->setData('integer', 123);
        $result->setData('float', 45.67);
        $result->setData('boolean', true);
        $result->setData('array', ['nested' => 'value']);
        $result->setData('null', null);

        $data = $result->getData();
        $this->assertEquals('value', $data['string']);
        $this->assertEquals(123, $data['integer']);
        $this->assertEquals(45.67, $data['float']);
        $this->assertTrue($data['boolean']);
        $this->assertEquals(['nested' => 'value'], $data['array']);
        $this->assertNull($data['null']);
    }

    /**
     * Test that setMeta() accepts various value types
     */
    public function test_set_meta_accepts_various_value_types(): void
    {
        $result = new TransitionResult();
        $result->setMeta('string', 'value');
        $result->setMeta('integer', 123);
        $result->setMeta('float', 45.67);
        $result->setMeta('boolean', true);
        $result->setMeta('array', ['nested' => 'value']);
        $result->setMeta('null', null);

        $meta = $result->getMeta();
        $this->assertEquals('value', $meta['string']);
        $this->assertEquals(123, $meta['integer']);
        $this->assertEquals(45.67, $meta['float']);
        $this->assertTrue($meta['boolean']);
        $this->assertEquals(['nested' => 'value'], $meta['array']);
        $this->assertNull($meta['null']);
    }

    /**
     * Test that addError() with markFailed=false preserves success state
     */
    public function test_add_error_with_mark_failed_false_preserves_success_state(): void
    {
        $result = new TransitionResult(true);
        $result->addError('Warning', false);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('ok', $result->getStatus());
        $this->assertTrue($result->hasErrors());
    }

    /**
     * Test that addError() with markFailed=true changes success state
     */
    public function test_add_error_with_mark_failed_true_changes_success_state(): void
    {
        $result = new TransitionResult(true);
        $result->addError('Error', true);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('failed', $result->getStatus());
        $this->assertTrue($result->hasErrors());
    }
}
