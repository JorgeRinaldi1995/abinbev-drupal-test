<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

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

  private function recordVote(VotingAnswerAssignment $assignment, int $uid): void {
    $existing_vote = $this->entityTypeManager->getStorage('vote_record')->loadByProperties([
      'assignment_id' => $assignment->id(),
      'user_id' => $uid,
    ]);

    if (!empty($existing_vote)) {
      throw new DuplicateVoteException('You have already voted on this question.');
    }

    $vote = $this->entityTypeManager->getStorage('vote_record')->create([
      'assignment_id' => $assignment->id(),
      'user_id' => $uid,
      'created' => time(),
    ]);
    $vote->save();

    $assignment->set('vote_count', $assignment->get('vote_count')->value + 1);
    $assignment->save();
  }

}
