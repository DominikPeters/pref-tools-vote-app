<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Option;

class OptionTest extends TestCase
{
    // =========================================================================
    // Basic CRUD Tests
    // =========================================================================

    public function test_can_create_and_find_option(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Option',
        ]);

        $this->assertNotNull($option->id);
        $this->assertEquals($question->id, $option->questionId);
        $this->assertEquals('Test Option', $option->label);
        $this->assertNotNull($option->createdAt);

        $found = Option::find($option->id);
        $this->assertEquals($option->label, $found->label);
    }

    public function test_can_create_option_with_description(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Option',
            'description' => 'A detailed description of this option',
        ]);

        $this->assertEquals('A detailed description of this option', $option->description);

        $fetched = Option::find($option->id);
        $this->assertEquals('A detailed description of this option', $fetched->description);
    }

    public function test_can_update_option(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Original Label',
            'description' => 'Original description',
        ]);

        $updated = $option->update([
            'label' => 'Updated Label',
            'description' => 'Updated description',
        ]);

        $this->assertEquals('Updated Label', $updated->label);
        $this->assertEquals('Updated description', $updated->description);
    }

    public function test_can_delete_option(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, ['label' => 'To be deleted']);
        $id = $option->id;

        $result = $option->delete();

        $this->assertTrue($result);
        $this->assertNull(Option::find($id));
    }

    public function test_find_returns_null_for_nonexistent_option(): void
    {
        $this->assertNull(Option::find(99999));
    }

    // =========================================================================
    // Query Tests
    // =========================================================================

    public function test_find_by_question_id(): void
    {
        $poll = $this->createPoll();
        // Create questions without default options
        $question1 = $this->createQuestion($poll->id, ['options' => []]);
        $question2 = $this->createQuestion($poll->id, ['options' => []]);

        Option::create($question1->id, ['label' => 'Q1 Option A']);
        Option::create($question1->id, ['label' => 'Q1 Option B']);
        Option::create($question2->id, ['label' => 'Q2 Option A']);

        $q1Options = Option::findByQuestionId($question1->id);
        $q2Options = Option::findByQuestionId($question2->id);

        $this->assertCount(2, $q1Options);
        $this->assertCount(1, $q2Options);
        $this->assertEquals('Q1 Option A', $q1Options[0]->label);
        $this->assertEquals('Q1 Option B', $q1Options[1]->label);
    }

    // =========================================================================
    // Sort Order Tests
    // =========================================================================

    public function test_sort_order_auto_increments(): void
    {
        $poll = $this->createPoll();
        // Create question without default options to control sort order
        $question = $this->createQuestion($poll->id, ['options' => []]);

        $option1 = Option::create($question->id, ['label' => 'First']);
        $option2 = Option::create($question->id, ['label' => 'Second']);
        $option3 = Option::create($question->id, ['label' => 'Third']);

        $this->assertEquals(0, $option1->sortOrder);
        $this->assertEquals(1, $option2->sortOrder);
        $this->assertEquals(2, $option3->sortOrder);
    }

    public function test_explicit_sort_order(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test',
            'sort_order' => 5,
        ]);

        $this->assertEquals(5, $option->sortOrder);
    }

    public function test_can_update_sort_order(): void
    {
        $poll = $this->createPoll();
        // Create question without default options to control sort order
        $question = $this->createQuestion($poll->id, ['options' => []]);

        $option = Option::create($question->id, ['label' => 'Test']);
        $this->assertEquals(0, $option->sortOrder);

        $updated = $option->update(['sort_order' => 10]);
        $this->assertEquals(10, $updated->sortOrder);
    }

    public function test_find_by_question_id_returns_sorted(): void
    {
        $poll = $this->createPoll();
        // Create question without default options
        $question = $this->createQuestion($poll->id, ['options' => []]);

        Option::create($question->id, ['label' => 'C', 'sort_order' => 2]);
        Option::create($question->id, ['label' => 'A', 'sort_order' => 0]);
        Option::create($question->id, ['label' => 'B', 'sort_order' => 1]);

        $options = Option::findByQuestionId($question->id);

        $this->assertEquals('A', $options[0]->label);
        $this->assertEquals('B', $options[1]->label);
        $this->assertEquals('C', $options[2]->label);
    }

    // =========================================================================
    // toArray Tests
    // =========================================================================

    public function test_to_array_includes_basic_fields(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Label',
            'description' => 'Test Description',
            'sort_order' => 3,
        ]);

        $array = $option->toArray();

        $this->assertEquals($option->id, $array['id']);
        $this->assertEquals('Test Label', $array['label']);
        $this->assertEquals('Test Description', $array['description']);
        $this->assertEquals(3, $array['sort_order']);
    }

    // =========================================================================
    // Features Tests
    // =========================================================================

    public function test_can_create_option_with_features(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Option',
            'features' => [
                'image_url' => 'https://example.com/img.png',
                'link' => 'https://example.com',
            ]
        ]);

        $this->assertNotNull($option->id);
        $this->assertEquals('Test Option', $option->label);
        $this->assertEquals([
            'image_url' => 'https://example.com/img.png',
            'link' => 'https://example.com',
        ], $option->features);
    }

    public function test_features_persist_after_fetch(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Option',
            'features' => ['custom_key' => 'custom_value']
        ]);

        $fetched = Option::find($option->id);

        $this->assertEquals(['custom_key' => 'custom_value'], $fetched->features);
    }

    public function test_features_included_in_to_array_when_set(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Option',
            'features' => ['key' => 'value']
        ]);

        $array = $option->toArray();

        $this->assertArrayHasKey('features', $array);
        $this->assertEquals(['key' => 'value'], $array['features']);
    }

    public function test_features_not_included_in_to_array_when_null(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Option',
        ]);

        $array = $option->toArray();

        $this->assertArrayNotHasKey('features', $array);
    }

    public function test_can_update_features(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Option',
            'features' => ['old' => 'value']
        ]);

        $updated = $option->update([
            'features' => ['new' => 'value', 'another' => 'field']
        ]);

        $this->assertEquals(['new' => 'value', 'another' => 'field'], $updated->features);
    }

    public function test_can_clear_features(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Option',
            'features' => ['key' => 'value']
        ]);

        $updated = $option->update(['features' => null]);

        $this->assertNull($updated->features);
    }

    public function test_option_without_features_has_null(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id);

        $option = Option::create($question->id, [
            'label' => 'Test Option',
        ]);

        $this->assertNull($option->features);
    }

    // =========================================================================
    // User-Added Option Tests (for "Other" feature)
    // =========================================================================

    public function test_find_or_create_user_added_creates_new_option(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id, ['options' => []]);

        $option = Option::findOrCreateUserAdded($question->id, 'Other: Pizza');

        $this->assertNotNull($option->id);
        $this->assertEquals('Other: Pizza', $option->label);
        $this->assertTrue($option->features['isUserAdded'] ?? false);
    }

    public function test_find_or_create_user_added_returns_existing_option(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id, ['options' => []]);

        // Create first
        $option1 = Option::findOrCreateUserAdded($question->id, 'Other: Pizza');

        // Find existing
        $option2 = Option::findOrCreateUserAdded($question->id, 'Other: Pizza');

        $this->assertEquals($option1->id, $option2->id);
        $this->assertEquals('Other: Pizza', $option2->label);
    }

    public function test_find_or_create_user_added_does_not_match_non_user_added(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id, ['options' => []]);

        // Create a regular option with the same label
        $regularOption = Option::create($question->id, ['label' => 'Other: Pizza']);
        $this->assertNull($regularOption->features);

        // Find or create user-added should create a NEW option
        $userAddedOption = Option::findOrCreateUserAdded($question->id, 'Other: Pizza');

        $this->assertNotEquals($regularOption->id, $userAddedOption->id);
        $this->assertTrue($userAddedOption->features['isUserAdded'] ?? false);
    }

    public function test_find_or_create_user_added_groups_identical_text(): void
    {
        $poll = $this->createPoll();
        $question = $this->createQuestion($poll->id, ['options' => []]);

        // Simulate two voters typing the same "other" value
        $voter1Option = Option::findOrCreateUserAdded($question->id, 'Other: Sushi');
        $voter2Option = Option::findOrCreateUserAdded($question->id, 'Other: Sushi');
        $voter3Option = Option::findOrCreateUserAdded($question->id, 'Other: Tacos'); // Different

        // Same text should return same option
        $this->assertEquals($voter1Option->id, $voter2Option->id);
        // Different text should create new option
        $this->assertNotEquals($voter1Option->id, $voter3Option->id);
        $this->assertEquals('Other: Tacos', $voter3Option->label);
    }
}
