<?php

namespace Drupal\voting_system\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * @Constraint(
 *   id = "UniqueQuestionIdentifier",
 *   label = @Translation("Unique question identifier")
 * )
 */
class UniqueQuestionIdentifierConstraint extends Constraint {

  public string $message = 'The question identifier "%value" is already in use.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return 'voting_system.unique_question_identifier_validator';
  }

}