<?php

namespace Drupal\Tests\voting_system\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\voting_system\Entity\VotingQuestion;

/**
 * Tests that voting question identifiers remain unique.
 *
 * @group voting_system
 */
class VotingQuestionUniquenessTest extends KernelTestBase {

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
  }

  /**
   * Ensures duplicate question identifiers are rejected.
   */
  public function testDuplicateQuestionIdentifierIsRejected(): void {
    $user = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->create([
        'name' => 'tester',
        'mail' => 'tester@example.com',
      ]);
    $user->save();

    $first_question = VotingQuestion::create([
      'title' => 'First question',
      'question_id' => 'bebida',
      'status' => 1,
      'user_id' => $user->id(),
    ]);
    $first_question->save();

    $second_question = VotingQuestion::create([
      'title' => 'Second question',
      'question_id' => 'bebida',
      'status' => 1,
      'user_id' => $user->id(),
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $second_question->save();
  }

}
