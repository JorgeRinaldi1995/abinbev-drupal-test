<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\voting_system\Entity\VotingAnswer;
use Drupal\voting_system\Entity\VotingAnswerAssignment;
use Drupal\voting_system\Exception\AnswerNotFoundException;
use Drupal\voting_system\Exception\AnswerQuestionMismatchException;
use Drupal\voting_system\Exception\DuplicateVoteException;
use Drupal\voting_system\Exception\QuestionNotActiveException;
use Drupal\voting_system\Entity\VotingQuestion;

/**
 * Records votes cast by users.
 */
class VoteService {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly QuestionResolverService $questionResolver,
    protected readonly Connection $database,
  ) {}

  /**
   * Casts a vote for an answer, identified by question + answer IDs.
   *
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   * @throws \Drupal\voting_system\Exception\QuestionNotActiveException
   * @throws \Drupal\voting_system\Exception\AnswerNotFoundException
   * @throws \Drupal\voting_system\Exception\AnswerQuestionMismatchException
   * @throws \Drupal\voting_system\Exception\DuplicateVoteException
   */
  public function submitVote(int $answer_id, int|string $question_identifier, int $uid): void {
    $answer = $this->entityTypeManager->getStorage('voting_answer')->load($answer_id);
    if (!$answer instanceof VotingAnswer) {
      throw new AnswerNotFoundException(sprintf('Answer %d not found.', $answer_id));
    }

    $question = $this->questionResolver->resolve($question_identifier);
    $this->assertQuestionIsActive($question);

    $assignment = $this->findAssignment((int) $question->id(), $answer_id);
    if (!$assignment) {
      throw new AnswerQuestionMismatchException('The answer is not linked to the provided question.');
    }

    $this->recordVote($assignment, $uid);
  }

  /**
   * Casts a vote for an answer, identified by its assignment ID.
   *
   * @throws \Drupal\voting_system\Exception\QuestionNotFoundException
   * @throws \Drupal\voting_system\Exception\QuestionNotActiveException
   * @throws \Drupal\voting_system\Exception\AnswerQuestionMismatchException
   * @throws \Drupal\voting_system\Exception\DuplicateVoteException
   */
  public function submitVoteByAssignment(int $assignment_id, int|string $question_identifier, int $uid): void {
    $question = $this->questionResolver->resolve($question_identifier);
    $this->assertQuestionIsActive($question);

    $assignment = $this->entityTypeManager->getStorage('voting_answer_assignment')->load($assignment_id);
    if (!$assignment instanceof VotingAnswerAssignment || (int) $assignment->get('question_id')->target_id !== (int) $question->id()) {
      throw new AnswerQuestionMismatchException('The selected answer is not linked to the provided question.');
    }

    $this->recordVote($assignment, $uid);
  }

  /**
   * @throws \Drupal\voting_system\Exception\QuestionNotActiveException
   */
  private function assertQuestionIsActive(VotingQuestion $question): void {
    if (!$question->get('status')->value) {
      throw new QuestionNotActiveException('This question is not accepting votes.');
    }
  }

  /**
   * Finds the assignment linking a question to a given answer, if any.
   */
  private function findAssignment(int $question_entity_id, int $answer_id): ?VotingAnswerAssignment {
    $assignments = $this->entityTypeManager->getStorage('voting_answer_assignment')->loadByProperties([
      'question_id' => $question_entity_id,
      'answer_id' => $answer_id,
    ]);

    return $assignments ? reset($assignments) : NULL;
  }

  /**
   * @throws \Drupal\voting_system\Exception\DuplicateVoteException
   */
  private function recordVote(VotingAnswerAssignment $assignment, int $uid): void {
    $assignment_id = (int) $assignment->id();

    // Fast-path only: doesn't prevent a duplicate under concurrency (two
    // requests could both pass this before either INSERT commits). The real
    // guarantee is the unique index from voting_system_update_10002(); a
    // racing duplicate is caught as an exception below.
    $existing_vote = $this->entityTypeManager->getStorage('vote_record')->loadByProperties([
      'assignment_id' => $assignment_id,
      'user_id' => $uid,
    ]);

    if (!empty($existing_vote)) {
      throw new DuplicateVoteException('You have already voted on this question.');
    }

    // Transaction: the vote row and the counter it feeds must land together,
    // or a failure between them would silently drift the tally.
    $transaction = $this->database->startTransaction();

    try {
      $vote = $this->entityTypeManager->getStorage('vote_record')->create([
        'assignment_id' => $assignment_id,
        'user_id' => $uid,
        'created' => time(),
      ]);
      $vote->save();

      // Atomic increment, not read-then-write: two concurrent votes on the
      // same answer could otherwise both read the same value and overwrite
      // each other's increment.
      $this->database->update('voting_answer_assignment')
        ->expression('vote_count', 'vote_count + 1')
        ->condition('id', $assignment_id)
        ->execute();

      // The raw update above bypasses the entity API, so its usual
      // bookkeeping doesn't happen: reset the entity cache (else a stale
      // vote_count keeps being served) and the list tag VotingQueryService
      // caches against (else its cached payloads go stale too).
      $this->entityTypeManager->getStorage('voting_answer_assignment')->resetCache([$assignment_id]);
      Cache::invalidateTags(['voting_answer_assignment_list']);
    }
    catch (\Exception $exception) {
      $transaction->rollBack();

      if ($exception instanceof IntegrityConstraintViolationException) {
        throw new DuplicateVoteException('You have already voted on this question.');
      }

      throw $exception;
    }
  }

}
