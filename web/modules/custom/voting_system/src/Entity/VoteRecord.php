<?php

namespace Drupal\voting_system\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;

/**
 * Defines the Vote Record entity.
 *
 * @ContentEntityType(
 *   id = "vote_record",
 *   label = @Translation("Vote Record"),
 *   base_table = "vote_record",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "user_id" = "user_id",
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "form" = {
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler"
 *   },
 *   admin_permission = "administer vote records",
 *   links = {
 *     "canonical" = "/admin/vote_record/{vote_record}",
 *     "edit-form" = "/admin/vote_record/{vote_record}/edit",
 *     "delete-form" = "/admin/vote_record/{vote_record}/delete"
 *   }
 * )
 */
class VoteRecord extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Reference to the user who voted.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user who submitted the vote.'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Reference to the specific question-answer assignment.
    // This ensures the vote is tied to a particular (question, answer)
    // pair when answers are reused across multiple questions.
    $fields['assignment_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Answer Assignment'))
      ->setDescription(t('Reference to the question-answer assignment this vote belongs to.'))
      ->setSetting('target_type', 'voting_answer_assignment')
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Creation date.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the vote was submitted.'))
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
