<?php

namespace Drupal\voting_system;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

class VotingAnswerListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['title'] = $this->t('Title');
    $header['question_id'] = $this->t('Question ID');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\voting_system\Entity\VotingAnswer $entity */

    $row = [];

    // Show VotingAnswer title as a link to the entity
    $row['title'] = $entity->toLink();

    // Get related VotingQuestion
    /** @var \Drupal\voting_system\Entity\VotingQuestion $question */
    $question = $entity->get('question_id')->entity;
    $row['question_id'] = $question ? $question->label() : $this->t('No question');

    return $row + parent::buildRow($entity); // Include operations column
  }
  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    $build['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add Voting Answer'),
        '#url' => \Drupal\Core\Url::fromRoute('entity.voting_answer.add_form'),
        '#attributes' => [
            'class' => ['button', 'button--primary']
        ],
        '#weight' => -10,
    ];

    return $build;
  }
}
