<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\PabulibExporter;

class PabulibExporterTest extends TestCase
{
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll([
            'title' => 'PB Test Poll',
            'description' => 'A test poll for Pabulib export'
        ]);
    }

    public function test_export_pb_format(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'participatory_budgeting',
            'text' => 'Spend the budget',
            'settings' => [
                'totalBudget' => 2000,
                'currency' => 'EUR',
                'minOptions' => 1,
                'maxOptions' => 3
            ],
            'options' => [
                ['label' => 'Project A', 'features' => ['cost' => 500], 'description' => 'Desc A'],
                ['label' => 'Project B', 'features' => ['cost' => 800], 'description' => 'Desc B'],
                ['label' => 'Project C', 'features' => ['cost' => 1200], 'description' => 'Desc C']
            ]
        ]);

        $o1 = $question->options[0]->id;
        $o2 = $question->options[1]->id;
        $o3 = $question->options[2]->id;

        // Voter 1: Projects A and B
        Response::create($this->poll->id, ['answers' => [$question->id => [$o1, $o2]]]);
        // Voter 2: Project C
        Response::create($this->poll->id, ['answers' => [$question->id => [$o3]]]);

        $responses = $this->loadResponses();
        $output = PabulibExporter::export($question, $responses, $this->poll);

        $this->assertStringContainsString('META', $output);
        $this->assertStringContainsString('description; PB Test Poll', $output);
        $this->assertStringContainsString('num_projects; 3', $output);
        $this->assertStringContainsString('num_votes; 2', $output);
        $this->assertStringContainsString('budget; 2000', $output);
        $this->assertStringContainsString('currency; EUR', $output);
        $this->assertStringContainsString('min_length; 1', $output);
        $this->assertStringContainsString('max_length; 3', $output);
        $this->assertStringContainsString('rule: unknown', $output);
        $this->assertStringContainsString('vote_type; approval', $output);

        $this->assertStringContainsString('PROJECTS', $output);
        $this->assertStringContainsString('1; 500; Project A; Desc A', $output);
        $this->assertStringContainsString('2; 800; Project B; Desc B', $output);
        $this->assertStringContainsString('3; 1200; Project C; Desc C', $output);

        $this->assertStringContainsString('VOTES', $output);
        $this->assertStringContainsString('1; 1,2', $output);
        $this->assertStringContainsString('2; 3', $output);
    }

    public function test_export_pb_sanitization(): void
    {
        $this->poll = $this->poll->update(['title' => "Title; with\nnewline"]);
        
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'participatory_budgeting',
            'text' => 'PB',
            'options' => [
                ['label' => "Opt; 1", 'features' => ['cost' => 100], 'description' => "Desc\r\nwith; semicolon"]
            ]
        ]);

        $output = PabulibExporter::export($question, [], $this->poll);

        $this->assertStringContainsString('description; Title, with newline', $output);
        $this->assertStringContainsString('1; 100; Opt, 1; Desc  with, semicolon', $output);
    }

    private function loadResponses(): array
    {
        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) {
            $r->loadAnswers();
        }
        return $responses;
    }
}
