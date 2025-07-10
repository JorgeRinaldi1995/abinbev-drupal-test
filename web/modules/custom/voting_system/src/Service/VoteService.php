<?php

namespace Drupal\voting_system\Service;

use Drupal\voting_system\Entity\VotingAnswer;
use Drupal\voting_system\Entity\VoteRecord;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VoteService {

  /**
   * Submit a vote for a specific answer ID.
   */
  public function submitVote(int $answer_id, AccountInterface $account): bool {
    $answer = VotingAnswer::load($answer_id);
    if (!$answer) {
      throw new \InvalidArgumentException('Answer not found.');
    }

    $question = $answer->get('question_id')->entity;
    if (!$question || !$question->get('status')->value) {
      throw new AccessDeniedHttpException('Voting is disabled for this question.');
    }

    $existing = \Drupal::entityTypeManager()
      ->getStorage('vote_record')
      ->loadByProperties([
        'user_id' => $account->id(),
        'question_id' => $question->id(),
      ]);

    if (!empty($existing)) {
        throw new AccessDeniedHttpException('You have already voted on this question.');
    }
    // Increase vote count
    // Record vote
    $vote = VoteRecord::create([
        'user_id' => $account->id(),
        'question_id' => $question->id(),
        'answer_id' => $answer->id(),
    ]);
    $vote->save();

    $answer->set('vote_count', $answer->get('vote_count')->value + 1);
    $answer->save();

    return TRUE;
  }

  /**
   * Returns results (vote count and percentage) for a question.
   */
  public function getResults(int $question_id): array {
    $answers = \Drupal::entityTypeManager()
      ->getStorage('voting_answer')
      ->loadByProperties(['question_id' => $question_id]);

    $results = [];
    $total_votes = array_sum(array_map(fn($a) => $a->get('vote_count')->value, $answers));

    foreach ($answers as $answer) {
      $count = $answer->get('vote_count')->value;
      $results[] = [
        'id' => $answer->id(),
        'title' => $answer->get('title')->value,
        'votes' => $count,
        'percent' => $total_votes > 0 ? round(($count / $total_votes) * 100, 2) : 0,
      ];
    }

    return [
      'total_votes' => $total_votes,
      'answers' => $results,
    ];
  }
}
