<?php

namespace Drupal\voting_system;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Provides a list builder for Voting Answer entities.
 */
class VotingAnswerListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['title'] = $this->t('Title');
    $header['question_id'] = $this->t('Question');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\voting_system\Entity\VotingAnswer $entity */
    $row['title'] = $entity->toLink();

    $question = $entity->get('question_id')->entity;
    $row['question_id'] = $question ? $question->label() : $this->t('No question');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    $build['add'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Voting Answer'),
      '#url' => Url::fromRoute('entity.voting_answer.add_form'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
      '#weight' => -10,
    ];

    return $build;
  }
}
