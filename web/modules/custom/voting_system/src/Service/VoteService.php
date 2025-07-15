<?php

namespace Drupal\voting_system\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\voting_system\Entity\VotingAnswer;

class VoteService {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public function submitVote(int $answer_id, int $uid): void {
    $answer = $this->entityTypeManager->getStorage('voting_answer')->load($answer_id);
    if (!$answer instanceof VotingAnswer) {
      throw new \Exception('Answer not found.');
    }

    $question_id = $answer->get('question_id')->target_id;

    $existing_vote = $this->entityTypeManager->getStorage('vote_record')->loadByProperties([
      'question_id' => $question_id,
      'user_id' => $uid,
    ]);

    if (!empty($existing_vote)) {
      throw new \Exception('You have already voted on this question.');
    }

    $vote = $this->entityTypeManager->getStorage('vote_record')->create([
      'question_id' => $question_id,
      'answer_id' => $answer_id,
      'user_id' => $uid,
      'created' => time(),
    ]);
    $vote->save();

    $answer->set('vote_count', $answer->get('vote_count')->value + 1);
    $answer->save();
  }

  public function getResults(int $question_id): array {
    $answers = $this->entityTypeManager->getStorage('voting_answer')->loadByProperties([
      'question_id' => $question_id,
    ]);

    $total_votes = 0;
    $results = [];

    foreach ($answers as $answer) {
      $count = (int) $answer->get('vote_count')->value;
      $total_votes += $count;
    }

    foreach ($answers as $answer) {
      $count = (int) $answer->get('vote_count')->value;
      $percent = $total_votes > 0 ? round(($count / $total_votes) * 100) : 0;

      $results['answers'][] = [
        'id' => $answer->id(),
        'title' => $answer->get('title')->value,
        'votes' => $count,
        'percent' => $percent,
      ];
    }

    $results['total_votes'] = $total_votes;
    return $results;
  }

  public function validateAnswerForQuestion(int $answer_id, int $question_id): bool {
    $answer = $this->entityTypeManager->getStorage('voting_answer')->load($answer_id);
    return $answer && (int) $answer->get('question_id')->target_id === $question_id;
  }
}
