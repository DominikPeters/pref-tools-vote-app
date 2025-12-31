<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Response;

// Register autoloader for apportionment
spl_autoload_register(function ($class) {
    $prefix = 'Apportionment\\';
    $base_dir = __DIR__ . '/../../lib/apportionment/php/src/';

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

use Apportionment\Instance;

class ApportionmentProfileBuilder
{
    /**
     * Build an Apportionment Instance from single_choice responses
     *
     * @param Question $question The single_choice question
     * @param Response[] $responses Array of Response objects with loaded answers
     * @param int $seats Number of seats to allocate
     * @return Instance
     */
    public static function fromSingleChoiceResponses(Question $question, array $responses, int $seats): Instance
    {
        // echo "DEBUG: processing " . count($responses) . " responses\n";
        $question->loadOptions();
        $options = $question->options;

        // Count votes for each option
        $voteCounts = [];
        $candidateNames = [];
        $candidateColors = [];
        $optionIdToIndex = [];
        
        foreach ($options as $index => $option) {
            $voteCounts[$index] = 0;
            $candidateNames[$index] = $option->label;
            $candidateColors[$index] = $option->color ?? 'var(--color-text-dim)';
            $optionIdToIndex[$option->id] = $index;
        }

        foreach ($responses as $response) {
            $answer = self::getAnswerForQuestion($response, $question->id);
            if ($answer === null) {
                continue;
            }

            $optionId = $answer->getValue();
            if ($optionId === null) {
                continue;
            }

            if (isset($optionIdToIndex[$optionId])) {
                $voteCounts[$optionIdToIndex[$optionId]]++;
            }
        }

        return new Instance($seats, $voteCounts, $candidateNames, $candidateColors);
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
