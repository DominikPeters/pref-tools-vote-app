<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Services\ABCProfileBuilder;
use AbcVoting\Profile;

class ABCProfileBuilderTest extends TestCase
{
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open']);
    }

    public function test_from_approval_responses(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;
        $optC = $question->options[2]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optB, $optC]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $profile = ABCProfileBuilder::fromApprovalResponses($question, $responses);

        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertEquals(2, $profile->count());
        $this->assertEquals(3, $profile->numCand);
        
        $voters = $profile->getVoters();
        // Voter 0 approved [0, 1] (A, B)
        $this->assertEquals([0, 1], $voters[0]->approved);
        // Voter 1 approved [1, 2] (B, C)
        $this->assertEquals([1, 2], $voters[1]->approved);
    }

    public function test_get_option_labels(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $labels = ABCProfileBuilder::getOptionLabels($question);
        
        $this->assertCount(2, $labels);
        $this->assertEquals('A', $labels[$question->options[0]->id]);
        $this->assertEquals('B', $labels[$question->options[1]->id]);
    }
}
