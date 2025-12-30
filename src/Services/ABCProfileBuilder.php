<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Response;

// Register autoloader for abcvoting
spl_autoload_register(function ($class) {
    $prefix = 'AbcVoting\\';
    $base_dir = __DIR__ . '/../../lib/abcvoting/php/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use AbcVoting\Profile;

class ABCProfileBuilder
{
    /**
     * Build an AbcVoting Profile from approval responses
     *
     * @param Question $question The approval question
     * @param Response[] $responses Array of Response objects with loaded answers
     * @return Profile
     */
    public static function fromApprovalResponses(Question $question, array $responses): Profile
    {
        $question->loadOptions();
        $options = $question->options;

        // Build candidate map: option ID -> index
        $candidateMap = [];
        $candidateNames = [];
        foreach ($options as $index => $option) {
            $candidateMap[$option->id] = $index;
            $candidateNames[$index] = $option->label;
        }

        $numCands = count($options);
        $profile = new Profile($numCands, array_values($candidateNames));

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $value = $answer->getValue();
            if ($value === null || !is_array($value)) {
                continue;
            }

            $approved = [];
            foreach ($value as $optionId) {
                if (isset($candidateMap[$optionId])) {
                    $approved[] = $candidateMap[$optionId];
                }
            }

            if (!empty($approved)) {
                $profile->addVoter($approved);
            }
        }

        return $profile;
    }

    /**
     * Build option ID to label map
     */
    public static function getOptionLabels(Question $question): array
    {
        $question->loadOptions();
        $labels = [];
        foreach ($question->options as $option) {
            $labels[$option->id] = $option->label;
        }
        return $labels;
    }

    /**
     * Get the answer for a specific question from a response
     */
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
