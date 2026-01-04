<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\Reports\ResponseMatrixReport;

class ResponseMatrixReportTest extends TestCase
{
    private Poll $poll;
    private ResponseMatrixReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open', 'visibility' => 'full']);
        $this->report = new ResponseMatrixReport();
    }

    public function test_get_metadata(): void
    {
        $this->assertEquals('response_matrix', $this->report->getType());
        $this->assertEquals('Response Matrix', $this->report->getName());
        $this->assertNotEmpty($this->report->getSupportedQuestionTypes());
    }

    public function test_compute_approval(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;

        Response::create($this->poll->id, [
            'voter_name' => 'Alice',
            'answers' => [$question->id => [$optA]]
        ]);
        Response::create($this->poll->id, [
            'voter_name' => 'Bob',
            'answers' => [$question->id => [$optA, $optB]]
        ]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, ['is_admin' => true]);

        $this->assertEquals(2, $result['total_responses']);
        $this->assertCount(2, $result['rows']);
        
        // Alice (row 0)
        $this->assertEquals('Alice', $result['rows'][0]['voter']);
        $this->assertEquals('check', $result['rows'][0]['cells'][0]['type']);
        $this->assertTrue($result['rows'][0]['cells'][0]['value']); // A selected
        $this->assertFalse($result['rows'][0]['cells'][1]['value']); // B not selected

        // Bob (row 1)
        $this->assertEquals('Bob', $result['rows'][1]['voter']);
        $this->assertTrue($result['rows'][1]['cells'][0]['value']); // A selected
        $this->assertTrue($result['rows'][1]['cells'][1]['value']); // B selected
    }

    public function test_compute_ranking(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;

        Response::create($this->poll->id, [
            'voter_name' => 'Alice',
            'answers' => [$question->id => [$optA, $optB]] // A > B
        ]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, ['is_admin' => true]);

        $this->assertEquals('rank', $result['rows'][0]['cells'][0]['type']);
        $this->assertEquals(1, $result['rows'][0]['cells'][0]['value']); // A is 1st
        $this->assertEquals(2, $result['rows'][0]['cells'][1]['value']); // B is 2nd
    }

    public function test_compute_star_and_grade(): void
    {
        // Test Stars
        $starQuestion = $this->createQuestion($this->poll->id, [
            'type' => 'star',
            'settings' => ['starCount' => 5],
            'options' => [['label' => 'A']]
        ]);
        Response::create($this->poll->id, ['answers' => [$starQuestion->id => [$starQuestion->options[0]->id => 4]]]);
        
        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();
        $result = $this->report->compute($starQuestion, [$responses[0]], ['is_admin' => true]);
        $this->assertEquals(4, $result['rows'][0]['cells'][0]['value']);
        $this->assertEquals('star', $result['rows'][0]['cells'][0]['type']);

        // Test Grades
        $gradeQuestion = $this->createQuestion($this->poll->id, [
            'type' => 'grade',
            'settings' => ['preset' => 'a-f'],
            'options' => [['label' => 'A']]
        ]);
        $responses[0]->update(['answers' => [$gradeQuestion->id => [$gradeQuestion->options[0]->id => 'A']]]);
        $responses[0]->loadAnswers();
        
        $result = $this->report->compute($gradeQuestion, [$responses[0]], ['is_admin' => true]);
        $this->assertEquals('A', $result['rows'][0]['cells'][0]['value']);
        $this->assertEquals('grade', $result['rows'][0]['cells'][0]['type']);
    }

    public function test_compute_yes_no_abstain(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'yes_no_abstain',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;
        $optC = $question->options[2]->id;

        Response::create($this->poll->id, [
            'answers' => [$question->id => [
                $optA => 'yes',
                $optB => 'no',
                $optC => 'abstain'
            ]]
        ]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = $this->report->compute($question, $responses, ['is_admin' => true]);

        $this->assertEquals('Y', $result['rows'][0]['cells'][0]['display']);
        $this->assertEquals('N', $result['rows'][0]['cells'][1]['display']);
        $this->assertEquals('A', $result['rows'][0]['cells'][2]['display']);
    }

    public function test_name_visibility_logic(): void
    {
        $question = $this->createQuestion($this->poll->id, ['type' => 'approval', 'options' => [['label' => 'A']]]);
        Response::create($this->poll->id, ['voter_name' => 'Secret Voter', 'answers' => [$question->id => []]]);
        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        // 1. Admin sees names
        $resultAdmin = $this->report->compute($question, $responses, ['is_admin' => true, 'poll_visibility' => 'hidden']);
        $this->assertEquals('Secret Voter', $resultAdmin['rows'][0]['voter']);
        $this->assertTrue($resultAdmin['show_names']);

        // 2. Public sees names when visibility is 'full'
        $resultPublicFull = $this->report->compute($question, $responses, ['is_admin' => false, 'poll_visibility' => 'full']);
        $this->assertEquals('Secret Voter', $resultPublicFull['rows'][0]['voter']);
        $this->assertTrue($resultPublicFull['show_names']);

        // 3. Public sees 'Voter 1' when visibility is 'anonymous'
        $resultPublicAnon = $this->report->compute($question, $responses, ['is_admin' => false, 'poll_visibility' => 'anonymous']);
        $this->assertEquals('Voter 1', $resultPublicAnon['rows'][0]['voter']);
        $this->assertFalse($resultPublicAnon['show_names']);
    }

    public function test_exclude_user_added_options(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'settings' => ['allowOther' => true],
            'options' => [['label' => 'A']]
        ]);
        
        \App\Models\Option::findOrCreateUserAdded($question->id, 'Other');
        $question->loadOptions(); // Ensure options are fresh

        $responses = []; // Empty responses for simplicity

        // Default: include user options
        $resultInclude = $this->report->compute($question, $responses, ['include_user_options' => true]);
        $this->assertCount(2, $resultInclude['options']);

        // Config: exclude user options
        $resultExclude = $this->report->compute($question, $responses, ['include_user_options' => false]);
        $this->assertCount(1, $resultExclude['options']);
    }
}
