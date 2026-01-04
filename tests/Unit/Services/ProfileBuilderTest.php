<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;
use App\Models\Answer;
use App\Services\ProfileBuilder;
use PrefVoting\Profile;
use PrefVoting\ProfileWithTies;
use PrefVoting\GradeProfile;

class ProfileBuilderTest extends TestCase
{
    private Poll $poll;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poll = $this->createPoll(['status' => 'open']);
    }

    public function test_from_ranking_responses_linear(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;
        $optC = $question->options[2]->id;

        // 2 votes for A > B > C
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB, $optC]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB, $optC]]]);
        // 1 vote for B > C > A
        Response::create($this->poll->id, ['answers' => [$question->id => [$optB, $optC, $optA]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $profile = ProfileBuilder::fromRankingResponses($question, $responses);

        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertEquals(3, $profile->numVoters);
        
        // Profiles are aggregated: 2 for [0, 1, 2], 1 for [1, 2, 0]
        [$rankings, $rcounts] = $profile->getRankingsCounts();
        $this->assertCount(2, $rankings);
    }

    public function test_from_ranking_responses_with_ties(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'ranking_with_ties',
            'options' => [['label' => 'A'], ['label' => 'B'], ['label' => 'C']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;
        $optC = $question->options[2]->id;

        // A=1, B=1, C=2 (A and B tied for first)
        Response::create($this->poll->id, ['answers' => [$question->id => [
            $optA => 1,
            $optB => 1,
            $optC => 2
        ]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $profile = ProfileBuilder::fromRankingResponses($question, $responses);

        $this->assertInstanceOf(ProfileWithTies::class, $profile);
        $this->assertEquals(1, $profile->numVoters);
    }

    public function test_get_approval_counts(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'approval',
            'options' => [['label' => 'A'], ['label' => 'B']]
        ]);

        $optA = $question->options[0]->id;
        $optB = $question->options[1]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$optA]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA, $optB]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = ProfileBuilder::getApprovalCounts($question, $responses);

        $this->assertEquals(2, $result['counts'][$optA]);
        $this->assertEquals(1, $result['counts'][$optB]);
        $this->assertEquals(2, $result['total']);
    }

    public function test_get_approval_counts_filtered_excludes_user_added(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'single_choice',
            'settings' => ['allowOther' => true],
            'options' => [['label' => 'A']]
        ]);

        $optA = $question->options[0]->id;
        
        // Add a user-added option manually
        $optOther = \App\Models\Option::findOrCreateUserAdded($question->id, 'Other Option');

        Response::create($this->poll->id, ['answers' => [$question->id => $optA]]);
        Response::create($this->poll->id, ['answers' => [$question->id => $optOther->id]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        // Include user added
        $resultAll = ProfileBuilder::getApprovalCountsFiltered($question, $responses, false);
        $this->assertArrayHasKey($optOther->id, $resultAll['counts']);
        $this->assertEquals(1, $resultAll['counts'][$optOther->id]);

        // Exclude user added
        $resultFiltered = ProfileBuilder::getApprovalCountsFiltered($question, $responses, true);
        $this->assertArrayNotHasKey($optOther->id, $resultFiltered['counts']);
        $this->assertEquals(1, $resultFiltered['counts'][$optA]);
    }

    public function test_from_grade_responses(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'grade',
            'settings' => ['preset' => 'a-f'],
            'options' => [['label' => 'A']]
        ]);

        $optA = $question->options[0]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 'A']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 'B']]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $profile = ProfileBuilder::fromGradeResponses($question, $responses);

        $this->assertInstanceOf(GradeProfile::class, $profile);
        $this->assertEquals(2, $profile->numVoters);
        $this->assertEquals(['A', 'B', 'C', 'D', 'E', 'F'], $profile->grades);
    }

    public function test_from_star_responses(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'star',
            'settings' => ['starCount' => 5],
            'options' => [['label' => 'A']]
        ]);

        $optA = $question->options[0]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 5]]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 3]]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $profile = ProfileBuilder::fromGradeResponses($question, $responses);

        $this->assertInstanceOf(GradeProfile::class, $profile);
        $this->assertEquals(2, $profile->numVoters);
        $this->assertEquals([5, 4, 3, 2, 1], $profile->grades);
    }

    public function test_get_yna_counts(): void
    {
        $question = $this->createQuestion($this->poll->id, [
            'type' => 'yes_no_abstain',
            'options' => [['label' => 'A']]
        ]);

        $optA = $question->options[0]->id;

        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 'yes']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 'no']]]);
        Response::create($this->poll->id, ['answers' => [$question->id => [$optA => 'abstain']]]);

        $responses = Response::findByPollId($this->poll->id);
        foreach ($responses as $r) $r->loadAnswers();

        $result = ProfileBuilder::getYNACounts($question, $responses);

        $this->assertEquals(1, $result['counts'][$optA]['yes']);
        $this->assertEquals(1, $result['counts'][$optA]['no']);
        $this->assertEquals(1, $result['counts'][$optA]['abstain']);
        $this->assertEquals(3, $result['total']);
    }

    public function test_get_grades_for_question(): void
    {
        $question = $this->createQuestion($this->poll->id, ['type' => 'grade', 'settings' => ['preset' => 'plus-minus']]);
        $this->assertEquals(['++', '+', '0', '−', '−−'], ProfileBuilder::getGradesForQuestion($question));

        $questionCustom = $this->createQuestion($this->poll->id, [
            'type' => 'grade', 
            'settings' => ['preset' => 'custom', 'grades' => ['Top', 'Bottom']]
        ]);
        $this->assertEquals(['Top', 'Bottom'], ProfileBuilder::getGradesForQuestion($questionCustom));
    }
}
