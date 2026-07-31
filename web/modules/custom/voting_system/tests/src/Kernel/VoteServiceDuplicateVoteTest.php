<?php

namespace Drupal\Tests\voting_system\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\voting_system\Entity\VotingAnswer;
use Drupal\voting_system\Entity\VotingAnswerAssignment;
use Drupal\voting_system\Entity\VotingQuestion;
use Drupal\voting_system\Exception\DuplicateVoteException;
use Drupal\voting_system\Service\VoteService;

/**
 * Tests duplicate vote handling in the vote service.
 *
 * @group voting_system
 */
class VoteServiceDuplicateVoteTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'voting_system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('voting_question');
    $this->installEntitySchema('voting_answer');
    $this->installEntitySchema('voting_answer_assignment');
    $this->installEntitySchema('vote_record');
  }

  /**
   * Ensures a second vote for the same assignment is rejected as a duplicate.
   */
  public function testDuplicateVoteThrowsSpecificException(): void {
    $user = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->create([
        'name' => 'tester',
        'mail' => 'tester@example.com',
      ]);
    $user->save();

    $question = VotingQuestion::create([
      'title' => 'Favorite drink',
      'question_id' => 'drink',
      'status' => 1,
    ]);
    $question->save();

    $answer = VotingAnswer::create([
      'title' => 'Beer',
      'description' => 'Lager',
    ]);
    $answer->save();

    $assignment = VotingAnswerAssignment::create([
      'question_id' => $question->id(),
      'answer_id' => $answer->id(),
      'vote_count' => 0,
    ]);
    $assignment->save();

    $service = $this->container->get(VoteService::class);

    $service->submitVoteByAssignment((int) $assignment->id(), $question->id(), (int) $user->id());

    $this->expectException(DuplicateVoteException::class);
    $service->submitVoteByAssignment((int) $assignment->id(), $question->id(), (int) $user->id());
  }

}
