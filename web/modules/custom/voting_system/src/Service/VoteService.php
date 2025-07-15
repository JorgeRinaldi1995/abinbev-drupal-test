<?php

namespace Drupal\voting_system\Service;

use Drupal\voting_system\Entity\VotingAnswer;
use Drupal\voting_system\Entity\VoteRecord;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles voting business logic for submitting and counting votes.
 */
class VoteService {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Submits a vote for the given answer ID on behalf of the current user.
   *
   * @param int $answer_id
   *   The ID of the selected answer.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the user has already voted or the question is disabled.
   */
  public function submitVote(int $answer_id, AccountInterface $account): bool {
    $answer = VotingAnswer::load($answer_id);
    if (!$answer) {
      throw new \InvalidArgumentException('Answer not found.');
    }

    $question = $answer->get('question_id')->entity ?? NULL;
    if (!$question || !$question->get('status')->value) {
      throw new AccessDeniedHttpException('Voting is disabled for this question.');
    }

    $vote_storage = $this->entityTypeManager->getStorage('vote_record');
    $existing = $vote_storage->loadByProperties([
      'user_id' => $account->id(),
      'question_id' => $question->id(),
    ]);

    if (!empty($existing)) {
      throw new AccessDeniedHttpException('You have already voted on this question.');
    }

    $vote = VoteRecord::create([
      'user_id' => $account->id(),
      'question_id' => $question->id(),
      'answer_id' => $answer->id(),
    ]);
    $vote->save();

    $current_votes = (int) $answer->get('vote_count')->value;
    $answer->set('vote_count', $current_votes + 1);
    $answer->save();

    return TRUE;
  }

  /**
   * Retrieves the voting results for a given question.
   *
   * @param int $question_id
   *   The question ID.
   *
   * @return array
   *   An array of answers with their vote counts and percentages.
   */
  public function getResults(int $question_id): array {
    $answers = $this->entityTypeManager
      ->getStorage('voting_answer')
      ->loadByProperties(['question_id' => $question_id]);

    $results = [];
    $total_votes = array_sum(array_map(fn($a) => (int) $a->get('vote_count')->value, $answers));

    foreach ($answers as $answer) {
      $count = (int) $answer->get('vote_count')->value;
      $results[] = [
        'id' => $answer->id(),
        'title' => $answer->get('title')->value ?? '',
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