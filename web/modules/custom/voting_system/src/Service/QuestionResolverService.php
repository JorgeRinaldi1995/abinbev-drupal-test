<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\voting_system\Entity\VotingQuestion;
use Drupal\voting_system\Exception\QuestionNotFoundException;

/**
 * Resolves a voting question from either its entity ID or its question_id.
 *
 * Both VoteService and VotingManagerService need to accept either identifier
 * form from API callers, so the lookup lives here once instead of being
 * duplicated across services.
 */
class QuestionResolverService {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Resolves a question by numeric entity ID or by its question_id string.
   *
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   */
  public function resolve(int|string $question_identifier): VotingQuestion {
    if (is_numeric($question_identifier)) {
      $question = $this->entityTypeManager->getStorage('voting_question')->load((int) $question_identifier);
      if ($question instanceof VotingQuestion) {
        return $question;
      }
    }

    $questions = $this->entityTypeManager->getStorage('voting_question')->loadByProperties([
      'question_id' => (string) $question_identifier,
    ]);

    if (empty($questions)) {
      throw new QuestionNotFoundException(sprintf('Question "%s" not found.', $question_identifier));
    }

    return reset($questions);
  }

  /**
   * Same as resolve(), but returns NULL instead of throwing.
   */
  public function findByIdentifier(int|string $question_identifier): ?VotingQuestion {
    try {
      return $this->resolve($question_identifier);
    }
    catch (QuestionNotFoundException) {
      return NULL;
    }
  }

}
