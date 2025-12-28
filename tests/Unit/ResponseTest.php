<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Response;
use App\Models\Poll;
use App\Models\Question;

class ResponseTest extends TestCase
{
    public function test_can_create_and_find_response(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id);
        
        $response = Response::create($poll->id, [
            'voter_name' => 'John Voter',
            'answers' => [
                $question->id => $question->options[0]->id
            ]
        ]);

        $this->assertNotNull($response->id);
        $this->assertEquals('John Voter', $response->voterName);
        $this->assertCount(1, $response->answers);

        $found = Response::find($response->id);
        $this->assertEquals('John Voter', $found->voterName);
        
        $byToken = Response::findByVoterToken($poll->id, $response->voterToken);
        $this->assertEquals($response->id, $byToken->id);
    }

    public function test_find_by_user_id(): void
    {
        $user = $this->createUser();
        $poll = $this->createPoll(['status' => 'open']);
        
        Response::create($poll->id, ['user_id' => $user->id]);
        Response::create($poll->id, ['user_id' => $user->id]);

        $responses = Response::findByUserId($user->id);
        $this->assertCount(2, $responses);
    }

    public function test_can_update_response(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id);
        $response = Response::create($poll->id, [
            'voter_name' => 'Original Name',
            'answers' => [$question->id => $question->options[0]->id]
        ]);

        $updated = $response->update([
            'voter_name' => 'Updated Name',
            'answers' => [$question->id => $question->options[1]->id]
        ]);

        $this->assertEquals('Updated Name', $updated->voterName);
        $this->assertCount(1, $updated->answers);
        $this->assertEquals($question->options[1]->id, $updated->answers[0]->getValue());
    }

    public function test_can_delete_response(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $response = Response::create($poll->id, []);
        $id = $response->id;

        $result = $response->delete();
        $this->assertTrue($result);
        $this->assertNull(Response::find($id));
    }

    public function test_verify_voter_token(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $response = Response::create($poll->id, []);
        
        $this->assertTrue($response->verifyVoterToken($response->voterToken));
        $this->assertFalse($response->verifyVoterToken('wrong-token'));
    }

    public function test_to_array(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        $question = $this->createQuestion($poll->id);
        $response = Response::create($poll->id, [
            'voter_name' => 'Alice',
            'answers' => [$question->id => 'My Answer']
        ]);

        $array = $response->toArray();
        $this->assertEquals('Alice', $array['voter_name']);
        $this->assertEquals('My Answer', $array['answers'][$question->id]);

        $anonymous = $response->toArray(false);
        $this->assertArrayNotHasKey('voter_name', $anonymous);
    }

    public function test_find_by_poll_id(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        Response::create($poll->id, []);
        Response::create($poll->id, []);

        $responses = Response::findByPollId($poll->id);
        $this->assertCount(2, $responses);
    }

    public function test_create_without_user_agent(): void
    {
        $poll = $this->createPoll(['status' => 'open']);
        
        // Unset User-Agent if it exists in the test environment
        $originalUA = $_SERVER['HTTP_USER_AGENT'] ?? null;
        unset($_SERVER['HTTP_USER_AGENT']);
        
        $response = Response::create($poll->id, []);
        $this->assertNull($response->userAgent);
        
        // Restore if needed
        if ($originalUA !== null) {
            $_SERVER['HTTP_USER_AGENT'] = $originalUA;
        }
    }
}
