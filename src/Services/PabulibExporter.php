<?php

namespace App\Services;

use App\Config;
use App\Models\Poll;
use App\Models\Question;
use App\Models\Response;

class PabulibExporter
{
    /**
     * Export a participatory budgeting question to Pabulib (.pb) format
     */
    public static function export(Question $question, array $responses, Poll $poll): string
    {
        $question->loadOptions();
        $options = $question->options;
        
        $numProjects = count($options);
        $numVotes = count($responses);
        
        $budget = $question->settings['totalBudget'] ?? 0;
        $currency = $question->settings['currency'] ?? '';
        $minLength = $question->settings['minOptions'] ?? null;
        $maxLength = $question->settings['maxOptions'] ?? null;

        $baseUrl = Config::get('app.url', 'http://localhost');
        $pollUrl = rtrim($baseUrl, '/') . '/' . $poll->publicId;

        $lines = [];
        $lines[] = 'META';
        $lines[] = 'key; value';
        $lines[] = 'description; ' . self::sanitize($poll->title);
        $lines[] = 'detailed_description; ' . self::sanitize($poll->description ?? '');
        $lines[] = 'country; N/A';
        $lines[] = 'unit; N/A';
        $lines[] = 'instance; ' . $pollUrl;
        $lines[] = 'num_projects; ' . $numProjects;
        $lines[] = 'num_votes; ' . $numVotes;
        $lines[] = 'budget; ' . $budget;
        $lines[] = 'rule: unknown'; // Following user example with colon
        $lines[] = 'vote_type; approval';
        
        if ($minLength !== null && $minLength > 0) {
            $lines[] = 'min_length; ' . $minLength;
        }
        if ($maxLength !== null && $maxLength > 0) {
            $lines[] = 'max_length; ' . $maxLength;
        }
        
        $lines[] = 'date_begin; ' . ($poll->createdAt ? $poll->createdAt->format('d.m.Y') : '');
        if ($poll->closedAt) {
            $lines[] = 'date_end; ' . $poll->closedAt->format('d.m.Y');
        }
        
        if (!empty($currency)) {
            $lines[] = 'currency; ' . $currency;
        }

        $lines[] = 'PROJECTS';
        $lines[] = 'project_id; cost; name; description';
        
        $optionMap = [];
        $idx = 1;
        foreach ($options as $option) {
            $cost = $option->features['cost'] ?? 0;
            $name = self::sanitize($option->label);
            $desc = self::sanitize($option->description ?? '');
            $lines[] = "{$idx}; {$cost}; {$name}; {$desc}";
            $optionMap[$option->id] = $idx;
            $idx++;
        }

        $lines[] = 'VOTES';
        $lines[] = 'voter_id; vote';
        
        $voterIdx = 1;
        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) continue;
            
            $value = $answer->getValue();
            if (!is_array($value)) continue;
            
            $votes = [];
            foreach ($value as $optionId) {
                if (isset($optionMap[$optionId])) {
                    $votes[] = $optionMap[$optionId];
                }
            }
            
            if (!empty($votes)) {
                $lines[] = "{$voterIdx}; " . implode(',', $votes);
            }
            $voterIdx++;
        }

        return implode("\n", $lines);
    }

    private static function sanitize(string $value): string
    {
        return str_replace(["\r", "\n", ";"], [" ", " ", ","], $value);
    }

    private static function getAnswerForQuestion(Response $response, int $questionId): ?\App\Models\Answer
    {
        if (empty($response->answers)) {
            $response->loadAnswers();
        }

        foreach ($response->answers as $answer) {
            if ($answer->questionId === $questionId) {
                return $answer;
            }
        }

        return null;
    }
}
