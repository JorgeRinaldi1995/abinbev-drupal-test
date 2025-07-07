<?php

namespace Drupal\voting_system\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

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
 *   },
 *   admin_permission = "administer vote records"
 * )
 */
class VoteRecord extends ContentEntityBase {
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['question_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Question'))
      ->setSetting('target_type', 'voting_question')
      ->setRequired(TRUE);

    $fields['answer_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Answer'))
      ->setSetting('target_type', 'voting_answer')
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    return $fields;
  }
}
