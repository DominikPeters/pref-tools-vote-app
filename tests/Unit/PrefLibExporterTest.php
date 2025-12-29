<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\PrefLibExporter;

class PrefLibExporterTest extends TestCase
{
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll();
    }

    // ========== SOC Format Tests (ranking) ==========

    public function test_export_soc_ranking(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'text' => 'Rank the candidates',
            'options' => [['label' => 'Alice'], ['label' => 'Bob'], ['label' => 'Carol']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;

        // Vote 1: Alice > Bob > Carol
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2, $o3]]]);
        // Vote 2: Alice > Bob > Carol (same)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2, $o3]]]);
        // Vote 3: Bob > Carol > Alice
        Response::create($this->poll->id, ['answers' => [$question->id => [$o2, $o3, $o1]]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertNotNull($output);
        $this->assertStringContainsString('# DATA TYPE: soc', $output);
        $this->assertStringContainsString('# NUMBER ALTERNATIVES: 3', $output);
        $this->assertStringContainsString('# NUMBER VOTERS: 3', $output);
        $this->assertStringContainsString('# NUMBER UNIQUE ORDERS: 2', $output);
        $this->assertStringContainsString('# ALTERNATIVE NAME 1: Alice', $output);
        $this->assertStringContainsString('# ALTERNATIVE NAME 2: Bob', $output);
        $this->assertStringContainsString('# ALTERNATIVE NAME 3: Carol', $output);
        $this->assertStringContainsString('2: 1, 2, 3', $output); // Alice > Bob > Carol (2 votes)
        $this->assertStringContainsString('1: 2, 3, 1', $output); // Bob > Carol > Alice (1 vote)
    }

    // ========== SOI Format Tests (ranking_truncated, single_choice) ==========

    public function test_export_soi_ranking_truncated(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking_truncated',
            'text' => 'Rank your top choices',
            'options' => [['label' => 'X'], ['label' => 'Y'], ['label' => 'Z'], ['label' => 'W']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;

        // Vote 1: X > Y (partial)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2]]]);
        // Vote 2: Z only
        Response::create($this->poll->id, ['answers' => [$question->id => [$o3]]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertNotNull($output);
        $this->assertStringContainsString('# DATA TYPE: soi', $output);
        $this->assertStringContainsString('# NUMBER ALTERNATIVES: 4', $output);
        $this->assertStringContainsString('# NUMBER VOTERS: 2', $output);
        $this->assertStringContainsString('1: 1, 2', $output); // X > Y
        $this->assertStringContainsString('1: 3', $output);    // Z only
    }

    public function test_export_soi_single_choice(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'single_choice',
            'text' => 'Pick one',
            'options' => [['label' => 'Red'], ['label' => 'Blue'], ['label' => 'Green']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;

        // Vote 1: Red
        Response::create($this->poll->id, ['answers' => [$question->id => $o1]]);
        // Vote 2: Blue
        Response::create($this->poll->id, ['answers' => [$question->id => $o2]]);
        // Vote 3: Blue
        Response::create($this->poll->id, ['answers' => [$question->id => $o2]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertNotNull($output);
        $this->assertStringContainsString('# DATA TYPE: soi', $output);
        $this->assertStringContainsString('# NUMBER VOTERS: 3', $output);
        $this->assertStringContainsString('# NUMBER UNIQUE ORDERS: 2', $output);
        $this->assertStringContainsString('1: 1', $output); // Red (1 vote)
        $this->assertStringContainsString('2: 2', $output); // Blue (2 votes)
    }

    // ========== TOI Format Tests (ranking_with_ties) ==========

    public function test_export_toi_ranking_with_ties(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking_with_ties',
            'text' => 'Rank with ties allowed',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C'], ['label' => 'D']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;
        $o4 = $question->options[3]->id;

        // Vote 1: A=1, B=1, C=2 (A and B tied first, C second)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1 => 1, $o2 => 1, $o3 => 2]]]);
        // Vote 2: D=1, A=2, B=2 (D first, A and B tied second)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o4 => 1, $o1 => 2, $o2 => 2]]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertNotNull($output);
        $this->assertStringContainsString('# DATA TYPE: toi', $output);
        $this->assertStringContainsString('# NUMBER ALTERNATIVES: 4', $output);
        $this->assertStringContainsString('# NUMBER VOTERS: 2', $output);
        // {1, 2} indicates tie between alternatives 1 and 2
        $this->assertStringContainsString('{1, 2}, 3', $output); // A,B tied, then C
        $this->assertStringContainsString('4, {1, 2}', $output); // D first, then A,B tied
    }

    // ========== CAT Format Tests (approval) ==========

    public function test_export_cat_approval(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'text' => 'Approve candidates',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;

        // Vote 1: Approve A and C
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o3]]]);
        // Vote 2: Approve B only
        Response::create($this->poll->id, ['answers' => [$question->id => [$o2]]]);
        // Vote 3: Approve A and C (same as vote 1)
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o3]]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertNotNull($output);
        $this->assertStringContainsString('# DATA TYPE: cat', $output);
        $this->assertStringContainsString('# NUMBER CATEGORIES: 2', $output);
        $this->assertStringContainsString('# CATEGORY NAME 1: Yes', $output);
        $this->assertStringContainsString('# CATEGORY NAME 2: No', $output);
        $this->assertStringContainsString('# NUMBER VOTERS: 3', $output);
        $this->assertStringContainsString('# NUMBER UNIQUE PREFERENCES: 2', $output);
        // Note: unrated alternatives are omitted, so "No" category shows as {}
        $this->assertStringContainsString('2: {1, 3}, {}', $output); // A,C approved (2 votes)
        $this->assertStringContainsString('1: 2, {}', $output);      // B approved (1 vote)
    }

    // ========== CAT Format Tests (yes_no_abstain) ==========

    public function test_export_cat_yes_no_abstain(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'yes_no_abstain',
            'text' => 'Vote on proposals',
            'settings' => ['allowAbstain' => true],
            'options' => [['label' => 'Proposal 1'], ['label' => 'Proposal 2']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;

        // Vote 1: Yes on P1, No on P2
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1 => 'yes', $o2 => 'no']]]);
        // Vote 2: Abstain on P1, Yes on P2
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1 => 'abstain', $o2 => 'yes']]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertNotNull($output);
        $this->assertStringContainsString('# DATA TYPE: cat', $output);
        $this->assertStringContainsString('# NUMBER CATEGORIES: 3', $output);
        $this->assertStringContainsString('# CATEGORY NAME 1: Yes', $output);
        $this->assertStringContainsString('# CATEGORY NAME 2: No', $output);
        $this->assertStringContainsString('# CATEGORY NAME 3: Abstain', $output);
    }

    public function test_export_cat_yes_no_only(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'yes_no_abstain',
            'text' => 'Vote yes or no',
            'settings' => ['allowAbstain' => false],
            'options' => [['label' => 'Item']]
        ]);

        $o1 = $question->options[0]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$o1 => 'yes']]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertStringContainsString('# NUMBER CATEGORIES: 2', $output);
        $this->assertStringNotContainsString('Abstain', $output);
    }

    // ========== CAT Format Tests (grade) ==========

    public function test_export_cat_grade(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'grade',
            'text' => 'Grade the options',
            'settings' => ['preset' => 'default'], // Excellent, Very Good, Good, Fair, Poor, Reject
            'options' => [['label' => 'Option A'], ['label' => 'Option B']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;

        // Vote 1: A=Excellent, B=Good
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1 => 'excellent', $o2 => 'good']]]);
        // Vote 2: A=Good, B=Excellent
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1 => 'good', $o2 => 'excellent']]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertNotNull($output);
        $this->assertStringContainsString('# DATA TYPE: cat', $output);
        $this->assertStringContainsString('# NUMBER CATEGORIES: 6', $output);
        $this->assertStringContainsString('# CATEGORY NAME 1: Excellent', $output);
        $this->assertStringContainsString('# CATEGORY NAME 2: Very Good', $output);
        $this->assertStringContainsString('# CATEGORY NAME 3: Good', $output);
    }

    public function test_export_cat_grade_custom(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'grade',
            'text' => 'Custom grades',
            'settings' => ['preset' => 'custom', 'grades' => ['Best', 'OK', 'Worst']],
            'options' => [['label' => 'X']]
        ]);

        $o1 = $question->options[0]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$o1 => 'Best']]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertStringContainsString('# NUMBER CATEGORIES: 3', $output);
        $this->assertStringContainsString('# CATEGORY NAME 1: Best', $output);
        $this->assertStringContainsString('# CATEGORY NAME 2: OK', $output);
        $this->assertStringContainsString('# CATEGORY NAME 3: Worst', $output);
    }

    // ========== CAT Format Tests (star) ==========

    public function test_export_cat_star(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'star',
            'text' => 'Rate with stars',
            'settings' => ['starCount' => 5],
            'options' => [['label' => 'Movie A'], ['label' => 'Movie B']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;

        // Vote 1: Movie A = 5 stars, Movie B = 3 stars
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1 => 5, $o2 => 3]]]);
        // Vote 2: Movie A = 4 stars, Movie B = 4 stars
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1 => 4, $o2 => 4]]]);

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertNotNull($output);
        $this->assertStringContainsString('# DATA TYPE: cat', $output);
        $this->assertStringContainsString('# NUMBER CATEGORIES: 5', $output);
        // Categories are from highest to lowest: 5, 4, 3, 2, 1
        $this->assertStringContainsString('# CATEGORY NAME 1: 5', $output);
        $this->assertStringContainsString('# CATEGORY NAME 2: 4', $output);
        $this->assertStringContainsString('# CATEGORY NAME 3: 3', $output);
    }

    // ========== Edge Cases ==========

    public function test_export_empty_responses(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'text' => 'Empty ranking',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $output = PrefLibExporter::export($question, []);

        $this->assertNotNull($output);
        $this->assertStringContainsString('# NUMBER VOTERS: 0', $output);
        $this->assertStringContainsString('# NUMBER UNIQUE ORDERS: 0', $output);
    }

    public function test_export_unsupported_type(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'text_single',
            'text' => 'Free text',
            'options' => []
        ]);

        $output = PrefLibExporter::export($question, []);

        $this->assertNull($output);
    }

    public function test_is_supported(): void
    {
        $supported = ['single_choice', 'ranking', 'ranking_truncated', 'ranking_with_ties',
                      'approval', 'yes_no_abstain', 'grade', 'star'];
        $unsupported = ['text_single', 'text_multi', 'section_header', 'utility'];

        foreach ($supported as $type) {
            $q = new Question();
            $q->type = $type;
            $this->assertTrue(PrefLibExporter::isSupported($q), "Expected $type to be supported");
        }

        foreach ($unsupported as $type) {
            $q = new Question();
            $q->type = $type;
            $this->assertFalse(PrefLibExporter::isSupported($q), "Expected $type to be unsupported");
        }
    }

    public function test_get_data_type(): void
    {
        $expected = [
            'single_choice' => 'soi',
            'ranking' => 'soc',
            'ranking_truncated' => 'soi',
            'ranking_with_ties' => 'toi',
            'approval' => 'cat',
            'yes_no_abstain' => 'cat',
            'grade' => 'cat',
            'star' => 'cat',
            'text_single' => null,
        ];

        foreach ($expected as $type => $dataType) {
            $q = new Question();
            $q->type = $type;
            $this->assertEquals($dataType, PrefLibExporter::getDataType($q), "Wrong data type for $type");
        }
    }

    public function test_metadata_override(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'text' => 'Original title',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $output = PrefLibExporter::export($question, [], [
            'title' => 'Custom Title',
            'description' => 'Custom Description',
            'file_name' => 'custom.soc'
        ]);

        $this->assertStringContainsString('# TITLE: Custom Title', $output);
        $this->assertStringContainsString('# DESCRIPTION: Custom Description', $output);
        $this->assertStringContainsString('# FILE NAME: custom.soc', $output);
    }

    public function test_ballot_aggregation(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'text' => 'Test aggregation',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;

        // Create 5 identical votes
        for ($i = 0; $i < 5; $i++) {
            Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2]]]);
        }

        $responses = $this->loadResponses();
        $output = PrefLibExporter::export($question, $responses);

        $this->assertStringContainsString('# NUMBER VOTERS: 5', $output);
        $this->assertStringContainsString('# NUMBER UNIQUE ORDERS: 1', $output);
        $this->assertStringContainsString('5: 1, 2', $output);
    }

    // ========== Helper Methods ==========

    private function loadResponses(): array
    {
        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) {
            $r->loadAnswers();
        }
        return $responses;
    }
}
