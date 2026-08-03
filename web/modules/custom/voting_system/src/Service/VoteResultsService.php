<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\voting_system\Exception\VoteRequiredException;

/**
 * Computes vote tallies for a question.
 *
 * Split out from VoteService so the write path (submitting a vote) and the
 * read path (reporting results) can change independently.
 */
class VoteResultsService {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly QuestionResolverService $questionResolver,
  ) {}

  /**
   * Results as exposed to public API callers: gated on the caller having
   * voted, and — unlike getResults() — replaced with a friendly message
   * (no numbers at all) when the question's `show_percent` is off.
   *
   * @return array<string, mixed>
   *
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   * @throws \Drupal\voting_system\Exception\VoteRequiredException
   */
  public function getResultsForVoter(int|string $question_identifier, int $uid): array {
    $question = $this->questionResolver->resolve($question_identifier);

    if (!$this->hasUserVoted((int) $question->id(), $uid)) {
      throw new VoteRequiredException('You must vote on this question before viewing its results.');
    }

    if (!$question->get('show_percent')->value) {
      return [
        'show_percent' => FALSE,
        'message' => 'Thank you for voting! Results for this question are not shown.',
      ];
    }

    return ['show_percent' => TRUE] + $this->getResults($question_identifier);
  }

  /**
   * Whether $uid has voted on any answer assigned to this question.
   */
  private function hasUserVoted(int $question_entity_id, int $uid): bool {
    $assignments = $this->entityTypeManager->getStorage('voting_answer_assignment')->loadByProperties([
      'question_id' => $question_entity_id,
    ]);

    if (!$assignments) {
      return FALSE;
    }

    $votes = $this->entityTypeManager->getStorage('vote_record')->loadByProperties([
      'user_id' => $uid,
      'assignment_id' => array_keys($assignments),
    ]);

    return !empty($votes);
  }

  /**
   * Unconditional vote tally for a question — no voter gate, no
   * show_percent check. Used internally and by VoteBlock, which applies
   * its own show_percent logic separately.
   *
   * @return array<string, mixed>
   *
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   */
  public function getResults(int|string $question_identifier): array {
    $question = $this->questionResolver->resolve($question_identifier);

    $assignments = $this->entityTypeManager->getStorage('voting_answer_assignment')->loadByProperties([
      'question_id' => $question->id(),
    ]);

    $total_votes = 0;
    foreach ($assignments as $assignment) {
      $total_votes += (int) $assignment->get('vote_count')->value;
    }

    $answers = [];
    foreach ($assignments as $assignment) {
      $answer = $assignment->get('answer_id')->entity;
      $count = (int) $assignment->get('vote_count')->value;

      $answers[] = [
        'id' => $answer ? $answer->id() : $assignment->get('answer_id')->target_id,
        'title' => $answer ? $answer->label() : '',
        'votes' => $count,
        'percent' => $total_votes > 0 ? round(($count / $total_votes) * 100) : 0,
      ];
    }

    return [
      'answers' => $answers,
      'total_votes' => $total_votes,
    ];
  }

}
