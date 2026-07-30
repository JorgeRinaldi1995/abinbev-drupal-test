<?php

namespace Drupal\voting_system\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;

/**
 * Defines the Voting Answer Assignment entity.
 *
 * This entity represents the many-to-many relationship between
 * `voting_question` and `voting_answer` and is responsible for
 * holding the vote tally for a specific (question, answer) pair.
 *
 * @ContentEntityType(
 *   id = "voting_answer_assignment",
 *   label = @Translation("Voting Answer Assignment"),
 *   base_table = "voting_answer_assignment",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     }
 *   },
 *   admin_permission = "administer voting answers",
 *   links = {
 *     "canonical" = "/admin/voting-answer-assignment/{voting_answer_assignment}",
 *     "add-form" = "/admin/voting-answer-assignment/add",
 *     "edit-form" = "/admin/voting-answer-assignment/{voting_answer_assignment}/edit",
 *     "delete-form" = "/admin/voting-answer-assignment/{voting_answer_assignment}/delete",
 *     "collection" = "/admin/voting-answer-assignment"
 *   }
 * )
 */
class VotingAnswerAssignment extends ContentEntityBase {
  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['question_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Question'))
      ->setDescription(t('The question this assignment belongs to.'))
      ->setSetting('target_type', 'voting_question')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['answer_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Answer'))
      ->setDescription(t('The answer this assignment refers to.'))
      ->setSetting('target_type', 'voting_answer')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['vote_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Vote Count'))
      ->setDescription(t('Number of votes received for this question-answer pair.'))
      ->setDefaultValue(0)
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
