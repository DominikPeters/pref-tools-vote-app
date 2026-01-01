<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Question;

class QuestionTest extends TestCase
{
    public function test_can_create_and_find_question(): void
    {
        $poll = $this->createPoll();
        $question = Question::create($poll->id, [
            'type' => 'single_choice',
            'text' => 'What is your favorite color?',
            'required' => true,
            'options' => [
                ['label' => 'Red'],
                ['label' => 'Blue'],
            ]
        ]);

        $this->assertNotNull($question->id);
        $this->assertEquals('single_choice', $question->type);
        $this->assertEquals('What is your favorite color?', $question->text);
        $this->assertTrue($question->required);
        $this->assertCount(2, $question->options);

        $found = Question::find($question->id);
        $this->assertEquals($question->text, $found->text);
    }

    public function test_can_update_question(): void
    {
        $poll = $this->createPoll();
        $question = Question::create($poll->id, ['text' => 'Old Text']);

        $updated = $question->update(['text' => 'New Text', 'required' => false]);

        $this->assertEquals('New Text', $updated->text);
        $this->assertFalse($updated->required);
    }

    public function test_can_delete_question(): void
    {
        $poll = $this->createPoll();
        $question = Question::create($poll->id, ['text' => 'To be deleted']);
        $id = $question->id;

        $result = $question->delete();

        $this->assertTrue($result);
        $this->assertNull(Question::find($id));
    }

    public function test_requires_options(): void
    {
        $poll = $this->createPoll();
        
        $q1 = Question::create($poll->id, ['type' => 'single_choice', 'text' => 'Choice']);
        $this->assertTrue($q1->requiresOptions());

        $q2 = Question::create($poll->id, ['type' => 'text_single', 'text' => 'Text']);
        $this->assertFalse($q2->requiresOptions());
        
        $q3 = Question::create($poll->id, ['type' => 'approval', 'text' => 'Approval']);
        $this->assertTrue($q3->requiresOptions());
    }

    public function test_validate_unknown_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown question type: invalid_type');

        Question::create(1, ['type' => 'invalid_type', 'text' => 'Invalid']);
    }

    public function test_validate_approval_settings(): void
    {
        $poll = $this->createPoll();

        // Valid settings
        $q = Question::create($poll->id, [
            'type' => 'approval',
            'text' => 'Approval',
            'settings' => ['min' => 1, 'max' => 2],
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);
        $this->assertEquals(1, $q->settings['min']);

        // Invalid: min > optionCount
        $this->expectException(\InvalidArgumentException::class);
        Question::create($poll->id, [
            'type' => 'approval',
            'text' => 'Approval',
            'settings' => ['min' => 5],
            'options' => [['label' => 'A']]
        ]);
    }

    public function test_validate_approval_max_less_than_min(): void
    {
        $poll = $this->createPoll();
        $this->expectException(\InvalidArgumentException::class);
        Question::create($poll->id, [
            'type' => 'approval',
            'text' => 'Approval',
            'settings' => ['min' => 3, 'max' => 1],
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);
    }

    public function test_validate_star_settings(): void
    {
        $poll = $this->createPoll();

        // Valid
        $q = Question::create($poll->id, [
            'type' => 'star',
            'text' => 'Rate',
            'settings' => ['starCount' => 5]
        ]);
        $this->assertEquals(5, $q->settings['starCount']);

        // Invalid: too few stars
        try {
            Question::create($poll->id, [
                'type' => 'star',
                'text' => 'Rate',
                'settings' => ['starCount' => 1]
            ]);
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Star count must be between 2 and 10', $e->getMessage());
        }

        // Invalid: too many stars
        try {
            Question::create($poll->id, [
                'type' => 'star',
                'text' => 'Rate',
                'settings' => ['starCount' => 11]
            ]);
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Star count must be between 2 and 10', $e->getMessage());
        }
    }

    public function test_validate_grade_settings(): void
    {
        $poll = $this->createPoll();

        // Valid
        $q = Question::create($poll->id, [
            'type' => 'grade',
            'text' => 'Grade',
            'settings' => ['grades' => ['A', 'B', 'C']]
        ]);
        $this->assertCount(3, $q->settings['grades']);

        // Invalid: grades not an array
        try {
            Question::create($poll->id, [
                'type' => 'grade',
                'text' => 'Grade',
                'settings' => ['grades' => 'not an array']
            ]);
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Grades must be an array', $e->getMessage());
        }

        // Invalid: empty grades
        try {
            Question::create($poll->id, [
                'type' => 'grade',
                'text' => 'Grade',
                'settings' => ['grades' => []]
            ]);
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('At least one grade is required', $e->getMessage());
        }
    }

    public function test_validate_yes_no_abstain_settings(): void
    {
        $poll = $this->createPoll();

        // Valid
        $q = Question::create($poll->id, [
            'type' => 'yes_no_abstain',
            'text' => 'Vote',
            'settings' => ['allowAbstain' => true]
        ]);
        $this->assertTrue($q->settings['allowAbstain']);

        // Invalid: allowAbstain not boolean
        try {
            Question::create($poll->id, [
                'type' => 'yes_no_abstain',
                'text' => 'Vote',
                'settings' => ['allowAbstain' => 'yes']
            ]);
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('allowAbstain must be a boolean', $e->getMessage());
        }
    }

    public function test_validate_approval_settings_non_numeric(): void
    {
        $poll = $this->createPoll();
        try {
            Question::create($poll->id, [
                'type' => 'approval',
                'text' => 'Approval',
                'settings' => ['min' => 'not a number']
            ]);
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Approval min must be a number', $e->getMessage());
        }

        try {
            Question::create($poll->id, [
                'type' => 'approval',
                'text' => 'Approval',
                'settings' => ['min' => 1, 'max' => 'not a number']
            ]);
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Approval max must be a number', $e->getMessage());
        }
    }

    public function test_validate_approval_min_negative(): void
    {
        $poll = $this->createPoll();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Approval min cannot be negative');
        Question::create($poll->id, [
            'type' => 'approval',
            'text' => 'Approval',
            'settings' => ['min' => -1]
        ]);
    }

    public function test_validate_approval_max_invalid(): void
    {
        $poll = $this->createPoll();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Approval max must be at least 1');
        Question::create($poll->id, [
            'type' => 'approval',
            'text' => 'Approval',
            'settings' => ['max' => 0]
        ]);
    }

    public function test_validate_star_non_numeric(): void
    {
        $poll = $this->createPoll();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Star count must be a number');
        Question::create($poll->id, [
            'type' => 'star',
            'text' => 'Rate',
            'settings' => ['starCount' => 'five']
        ]);
    }

    public function test_validate_grade_invalid_items(): void
    {
        $poll = $this->createPoll();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each grade must be a non-empty string');
        Question::create($poll->id, [
            'type' => 'grade',
            'text' => 'Grade',
            'settings' => ['grades' => ['A', '', 'C']]
        ]);
    }

    public function test_to_array(): void
    {
        $poll = $this->createPoll();
        $q = Question::create($poll->id, [
            'text' => 'Question Text',
            'options' => [['label' => 'Opt 1']]
        ]);

        $array = $q->toArray();
        $this->assertEquals('Question Text', $array['text']);
        $this->assertCount(1, $array['options']);
        $this->assertEquals('Opt 1', $array['options'][0]['label']);
    }

    public function test_find_by_poll_id(): void
    {
        $poll = $this->createPoll();
        Question::create($poll->id, ['text' => 'Q1', 'sort_order' => 1]);
        Question::create($poll->id, ['text' => 'Q2', 'sort_order' => 2]);

        $questions = Question::findByPollId($poll->id);
        $this->assertCount(2, $questions);
        $this->assertEquals('Q1', $questions[0]->text);
        $this->assertEquals('Q2', $questions[1]->text);
    }

    public function test_validate_single_choice_allow_other(): void
    {
        $poll = $this->createPoll();

        // Valid: allowOther as true
        $q = Question::create($poll->id, [
            'type' => 'single_choice',
            'text' => 'Favorite food',
            'settings' => ['allowOther' => true],
            'options' => [['label' => 'Pizza'], ['label' => 'Burger']]
        ]);
        $this->assertTrue($q->settings['allowOther']);

        // Valid: allowOther as false
        $q2 = Question::create($poll->id, [
            'type' => 'single_choice',
            'text' => 'Favorite drink',
            'settings' => ['allowOther' => false],
            'options' => [['label' => 'Coffee'], ['label' => 'Tea']]
        ]);
        $this->assertFalse($q2->settings['allowOther']);

        // Invalid: allowOther not boolean
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('allowOther must be a boolean');
        Question::create($poll->id, [
            'type' => 'single_choice',
            'text' => 'Favorite color',
            'settings' => ['allowOther' => 'yes'],
            'options' => [['label' => 'Red'], ['label' => 'Blue']]
        ]);
    }

    public function test_validate_approval_allow_other(): void
    {
        $poll = $this->createPoll();

        // Valid: allowOther with other settings
        $q = Question::create($poll->id, [
            'type' => 'approval',
            'text' => 'Select foods you like',
            'settings' => ['min' => 1, 'max' => 3, 'allowOther' => true],
            'options' => [['label' => 'Pizza'], ['label' => 'Burger'], ['label' => 'Salad']]
        ]);
        $this->assertTrue($q->settings['allowOther']);
        $this->assertEquals(1, $q->settings['min']);

        // Invalid: allowOther not boolean
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('allowOther must be a boolean');
        Question::create($poll->id, [
            'type' => 'approval',
            'text' => 'Select drinks',
            'settings' => ['allowOther' => 1],
            'options' => [['label' => 'Coffee'], ['label' => 'Tea']]
        ]);
    }
}
