<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

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
