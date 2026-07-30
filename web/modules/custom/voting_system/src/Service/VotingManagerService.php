<?php

namespace Drupal\voting_system\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\voting_system\Entity\VotingQuestion;
use Drupal\voting_system\Entity\VotingAnswer;

class VotingManagerService {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public function loadActiveQuestions(): array {
    return $this->entityTypeManager
      ->getStorage('voting_question')
      ->loadByProperties(['status' => 1]);
  }

  public function loadQuestionByIdentifier(int|string $question_identifier): ?VotingQuestion {
    if (is_numeric($question_identifier)) {
      $question = $this->entityTypeManager->getStorage('voting_question')->load((int) $question_identifier);
      if ($question instanceof VotingQuestion) {
        return $question;
      }
    }

    $questions = $this->entityTypeManager->getStorage('voting_question')->loadByProperties([
      'question_id' => (string) $question_identifier,
    ]);

    return !empty($questions) ? reset($questions) : NULL;
  }

  public function createQuestion(string $title, string $question_id, bool $show_percent, int $user_id): VotingQuestion {
    $existing_question = $this->loadQuestionByIdentifier($question_id);
    if ($existing_question instanceof VotingQuestion) {
      throw new \InvalidArgumentException('The provided question identifier already exists.');
    }

    $question = VotingQuestion::create([
      'title' => $title,
      'question_id' => $question_id,
      'show_percent' => $show_percent,
      'status' => 1,
      'user_id' => $user_id,
    ]);
    $question->save();
    return $question;
  }

  public function createAnswer(string $title, string $description, int|string $question_identifier): VotingAnswer {
    $question = $this->loadQuestionByIdentifier($question_identifier);
    if (!$question instanceof VotingQuestion) {
      throw new \InvalidArgumentException('Question not found.');
    }

    $answer = VotingAnswer::create([
      'title' => $title,
      'description' => $description,
    ]);
    $answer->save();

    $assignment = $this->entityTypeManager->getStorage('voting_answer_assignment')->create([
      'question_id' => $question->id(),
      'answer_id' => $answer->id(),
      'vote_count' => 0,
    ]);
    $assignment->save();

    return $answer;
  }
}
