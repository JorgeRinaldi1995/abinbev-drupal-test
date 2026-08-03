<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

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

    // Fast-path check: gives a clean error for the common (non-racing)
    // case without touching the database. It is NOT what actually prevents
    // a duplicate vote under concurrency — two simultaneous requests from
    // the same user could both pass this before either INSERT commits. The
    // real guarantee is the unique index on vote_record(user_id,
    // assignment_id) (see voting_system_update_10002()); a racing duplicate
    // is rejected by the database and caught below.
    $existing_vote = $this->entityTypeManager->getStorage('vote_record')->loadByProperties([
      'assignment_id' => $assignment_id,
      'user_id' => $uid,
    ]);

    if (!empty($existing_vote)) {
      throw new DuplicateVoteException('You have already voted on this question.');
    }

    // The vote record and the vote_count it feeds into must land together:
    // if the counter update failed after the vote was recorded (or vice
    // versa), the tally would silently drift from the actual vote rows.
    $transaction = $this->database->startTransaction();

    try {
      $vote = $this->entityTypeManager->getStorage('vote_record')->create([
        'assignment_id' => $assignment_id,
        'user_id' => $uid,
        'created' => time(),
      ]);
      $vote->save();

      // Atomic increment (vote_count = vote_count + 1) instead of reading
      // the current value and saving it back through the entity API: a
      // read-then-write is not safe under concurrent votes for the same
      // answer, since two requests can read the same value and both write
      // back current+1, losing one of the votes from the tally.
      $this->database->update('voting_answer_assignment')
        ->expression('vote_count', 'vote_count + 1')
        ->condition('id', $assignment_id)
        ->execute();
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
