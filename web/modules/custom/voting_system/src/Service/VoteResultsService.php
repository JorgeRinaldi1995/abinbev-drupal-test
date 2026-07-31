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
   * Results as exposed to public API callers.
   *
   * Unlike getResults(), this is gated on the caller having already voted
   * on the question, and strips the per-answer percentage when the
   * question's `show_percent` setting is off.
   *
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   * @throws \Drupal\voting_system\Exception\VoteRequiredException
   */
  public function getResultsForVoter(int|string $question_identifier, int $uid): array {
    $question = $this->questionResolver->resolve($question_identifier);

    if (!$this->hasUserVoted((int) $question->id(), $uid)) {
      throw new VoteRequiredException('You must vote on this question before viewing its results.');
    }

    $results = $this->getResults($question_identifier);
    $show_percent = (bool) $question->get('show_percent')->value;

    if (!$show_percent) {
      foreach ($results['answers'] as &$answer) {
        unset($answer['percent']);
      }
    }

    return ['show_percent' => $show_percent] + $results;
  }

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
