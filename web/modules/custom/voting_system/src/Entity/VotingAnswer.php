<?php

namespace Drupal\voting_system\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Voting Answer entity.
 *
 * @ContentEntityType(
 *   id = "voting_answer",
 *   label = @Translation("Voting Answer"),
 *   base_table = "voting_answer",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\voting_system\VotingAnswerListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *   },
 *   admin_permission = "administer voting answers",
 *   links = {
 *     "canonical" = "/admin/voting-answer/{voting_answer}",
 *     "add-form" = "/admin/voting-answer/add",
 *     "edit-form" = "/admin/voting-answer/{voting_answer}/edit",
 *     "delete-form" = "/admin/voting-answer/{voting_answer}/delete",
 *     "collection" = "/admin/voting-answer"
 *   }
 * )
 */
class VotingAnswer extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Answer title'))
      ->setRequired(TRUE)
      ->setSettings(['max_length' => 255])
      ->setDisplayOptions('form', ['type' => 'string_textfield']);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDisplayOptions('form', ['type' => 'text_textarea']);

    $fields['image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Answer Image'))
      ->setSettings([
        'file_extensions' => 'png jpg jpeg',
        'alt_field_required' => FALSE,
      ])
      ->setDisplayOptions('form', ['type' => 'image_image']);

    $fields['question_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Related Question'))
      ->setSetting('target_type', 'voting_question')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', ['type' => 'options_select']);

    $fields['vote_count'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Vote Count'))
      ->setDefaultValue(0)
      ->setReadOnly(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    return $fields;
  }
}
