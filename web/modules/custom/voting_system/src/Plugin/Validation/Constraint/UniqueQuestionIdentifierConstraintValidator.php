<?php

namespace Drupal\voting_system\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueQuestionIdentifierConstraintValidator extends ConstraintValidator {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  public function validate($value, Constraint $constraint): void {

    $field_item_list = $this->context->getObject();

    if ($field_item_list->isEmpty()) {
      return;
    }

    // Pega o valor real do campo string.
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

    // Ignora a própria entidade na edição.
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