<?php

namespace Drupal\voting_system\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\voting_system\Entity\VotingAnswer;

class VoteService {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  private function resolveQuestionEntityId(int|string $question_identifier): int {
    if (is_numeric($question_identifier)) {
      $question = $this->entityTypeManager->getStorage('voting_question')->load((int) $question_identifier);
      if ($question instanceof \Drupal\voting_system\Entity\VotingQuestion) {
        return (int) $question->id();
      }
    }

    $questions = $this->entityTypeManager->getStorage('voting_question')->loadByProperties([
      'question_id' => (string) $question_identifier,
    ]);

    if (empty($questions)) {
      throw new \Exception('Question not found.');
    }

    return (int) reset($questions)->id();
  }

  public function submitVote(int $answer_id, int|string $question_identifier, int $uid): void {
    $answer = $this->entityTypeManager->getStorage('voting_answer')->load($answer_id);
    if (!$answer instanceof VotingAnswer) {
      throw new \Exception('Answer not found.');
    }

    $question_entity_id = $this->resolveQuestionEntityId($question_identifier);

    $assignments = $this->entityTypeManager->getStorage('voting_answer_assignment')->loadByProperties([
      'question_id' => $question_entity_id,
      'answer_id' => $answer_id,
    ]);

    if (empty($assignments)) {
      throw new \Exception('The answer is not linked to the provided question.');
    }

    $assignment = reset($assignments);
    $this->submitVoteByAssignment((int) $assignment->id(), $question_entity_id, $uid);
  }

  public function submitVoteByAssignment(int $assignment_id, int|string $question_identifier, int $uid): void {
    $question_entity_id = $this->resolveQuestionEntityId($question_identifier);

    $assignment = $this->entityTypeManager->getStorage('voting_answer_assignment')->load($assignment_id);
    if (!$assignment || (int) $assignment->get('question_id')->target_id !== $question_entity_id) {
      throw new \Exception('The selected answer is not linked to the provided question.');
    }

    $existing_vote = $this->entityTypeManager->getStorage('vote_record')->loadByProperties([
      'assignment_id' => $assignment->id(),
      'user_id' => $uid,
    ]);

    if (!empty($existing_vote)) {
      throw new \Exception('You have already voted on this question.');
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

  public function getResults(int|string $question_identifier): array {
    $question_entity_id = $this->resolveQuestionEntityId($question_identifier);

    $assignments = $this->entityTypeManager->getStorage('voting_answer_assignment')->loadByProperties([
      'question_id' => $question_entity_id,
    ]);

    $total_votes = 0;
    $results = [];

    foreach ($assignments as $assignment) {
      $count = (int) $assignment->get('vote_count')->value;
      $total_votes += $count;
    }

    foreach ($assignments as $assignment) {
      $answer = $assignment->get('answer_id')->entity;
      $count = (int) $assignment->get('vote_count')->value;
      $percent = $total_votes > 0 ? round(($count / $total_votes) * 100) : 0;

      $results['answers'][] = [
        'id' => $answer ? $answer->id() : $assignment->get('answer_id')->target_id,
        'title' => $answer ? $answer->label() : '',
        'votes' => $count,
        'percent' => $percent,
      ];
    }

    $results['total_votes'] = $total_votes;
    return $results;
  }

  public function validateAnswerForQuestion(int $answer_id, int|string $question_identifier): bool {
    $question_entity_id = $this->resolveQuestionEntityId($question_identifier);

    $assignments = $this->entityTypeManager->getStorage('voting_answer_assignment')->loadByProperties([
      'question_id' => $question_entity_id,
      'answer_id' => $answer_id,
    ]);
    return !empty($assignments);
  }
}
