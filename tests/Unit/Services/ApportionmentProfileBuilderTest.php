<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ApportionmentProfileBuilder;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;

class ApportionmentProfileBuilderTest extends TestCase
{
    public function test_from_single_choice_responses(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'single_choice',
            'options' => [
                ['label' => 'Party A'],
                ['label' => 'Party B'],
                ['label' => 'Party C'],
            ]
        ]);
        $question->loadOptions();
        $optA = $question->options[0];
        $optB = $question->options[1];
        $optC = $question->options[2];

        // 3 votes for A, 2 for B, 1 for C
        Response::create($poll->id, ['answers' => [$question->id => $optA->id]]);
        Response::create($poll->id, ['answers' => [$question->id => $optA->id]]);
        Response::create($poll->id, ['answers' => [$question->id => $optA->id]]);
        Response::create($poll->id, ['answers' => [$question->id => $optB->id]]);
        Response::create($poll->id, ['answers' => [$question->id => $optB->id]]);
        Response::create($poll->id, ['answers' => [$question->id => $optC->id]]);

        $responses = Response::findByPollId($poll->id);
        
        $instance = ApportionmentProfileBuilder::fromSingleChoiceResponses($question, $responses, 10);

        $this->assertEquals(10, $instance->seats);
        $this->assertEquals([3, 2, 1], $instance->votes);
        $this->assertEquals(['Party A', 'Party B', 'Party C'], $instance->partyNames);
    }

    public function test_from_single_choice_responses_exclude_user_added(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'single_choice',
            'options' => [
                ['label' => 'Party A'],
                ['label' => 'Other', 'features' => ['isUserAdded' => true]],
            ]
        ]);
        $question->loadOptions();
        $optA = $question->options[0];
        $optOther = $question->options[1];

        Response::create($poll->id, ['answers' => [$question->id => $optA->id]]);
        Response::create($poll->id, ['answers' => [$question->id => $optOther->id]]);

        $responses = Response::findByPollId($poll->id);
        
        $instance = ApportionmentProfileBuilder::fromSingleChoiceResponses($question, $responses, 10, true);

        $this->assertEquals([1], $instance->votes);
        $this->assertEquals(['Party A'], $instance->partyNames);
    }

    public function test_from_single_choice_responses_empty_responses(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id, [
            'type' => 'single_choice',
            'options' => [['label' => 'Party A']]
        ]);
        
        $instance = ApportionmentProfileBuilder::fromSingleChoiceResponses($question, [], 10);
        $this->assertEquals([0], $instance->votes);
    }
}
