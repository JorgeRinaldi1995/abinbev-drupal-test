<?php

namespace Drupal\voting_system\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validator for the UniqueQuestionIdentifier constraint.
 */
class UniqueQuestionIdentifierConstraintValidator extends ConstraintValidator {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {

    $field_item_list = $this->context->getObject();

    if ($field_item_list->isEmpty()) {
      return;
    }

    $value = $field_item_list->value;

    if (!$value) {
      return;
    }

    $entity = $field_item_list->getEntity();

    $query = $this->entityTypeManager
      ->getStorage('voting_question')
      ->getQuery()
      ->condition('question_id', $value)
      ->accessCheck(FALSE);

    // Exclude the entity being edited, so it doesn't collide with itself.
    if (!$entity->isNew()) {
      $query->condition('id', $entity->id(), '<>');
    }

    if ($query->execute()) {
      $this->context
        ->buildViolation($constraint->message)
        ->setParameter('%value', $value)
        ->addViolation();
    }

  }

}