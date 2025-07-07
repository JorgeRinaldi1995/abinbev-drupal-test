<?php

namespace Drupal\voting_system;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

class VotingQuestionListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['title'] = $this->t('Title');
    $header['question_id'] = $this->t('Question ID');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
    public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\voting_system\Entity\VotingQuestion $entity */

    $row = [];
    $row['title'] = $entity->toLink();
    $row['question_id'] = $entity->get('question_id')->value;
    $row['status'] = $entity->get('status')->value ? $this->t('Active') : $this->t('Inactive');

    return $row + parent::buildRow($entity); // <- This ensures operations column is included
    }
  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    $build['add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add Voting Question'),
        '#url' => \Drupal\Core\Url::fromRoute('entity.voting_question.add_form'),
        '#attributes' => [
            'class' => ['button', 'button--primary']
        ],
        '#weight' => -10,
    ];

    return $build;
  }
}
