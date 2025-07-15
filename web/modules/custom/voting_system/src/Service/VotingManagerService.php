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

  public function createQuestion(string $title, string $question_id, bool $show_percent, int $user_id): VotingQuestion {
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

  public function createAnswer(string $title, string $description, string $question_id): VotingAnswer {
    $answer = VotingAnswer::create([
      'title' => $title,
      'description' => $description,
      'question_id' => $question_id,
      'vote_count' => 0,
    ]);
    $answer->save();
    return $answer;
  }
}
