<?php

namespace Drupal\voting_system\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;

/**
 * Defines the Voting Question entity.
 *
 * @ContentEntityType(
 *   id = "voting_question",
 *   label = @Translation("Voting Question"),
 *   base_table = "voting_question",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\voting_system\VotingQuestionListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *   },
 *   admin_permission = "administer voting questions",
 *   links = {
 *     "canonical" = "/admin/voting-question/{voting_question}",
 *     "add-form" = "/admin/voting-question/add",
 *     "edit-form" = "/admin/voting-question/{voting_question}/edit",
 *     "delete-form" = "/admin/voting-question/{voting_question}/delete",
 *     "collection" = "/admin/voting-question"
 *   }
 * )
 */
class VotingQuestion extends ContentEntityBase implements EntityOwnerInterface {
  use EntityOwnerTrait;

  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Question title'))
      ->setRequired(TRUE)
      ->setSettings(['max_length' => 255])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string'])
      ->setDisplayOptions('form', ['type' => 'string_textfield']);

    $fields['question_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Unique Question ID'))
      ->setRequired(TRUE)
      ->setSettings(['max_length' => 64])
      ->setDisplayOptions('form', ['type' => 'string_textfield']);

    $fields['show_percent'] = BaseFieldDefinition::create('boolean')
        ->setLabel(t('Show vote percentage after voting'))
        ->setDefaultValue(TRUE)
        ->setDisplayOptions('form', [
            'type' => 'boolean_checkbox',
            'weight' => 20,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', FALSE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', ['type' => 'boolean_checkbox']);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'));

    // Necessário para EntityOwnerInterface
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the question author.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getCurrentUserId')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', ['label' => 'hidden', 'type' => 'author'])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => ['match_operator' => 'CONTAINS', 'size' => '60'],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }
}