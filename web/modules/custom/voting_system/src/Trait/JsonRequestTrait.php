<?php

namespace Drupal\voting_system\Trait;

use Symfony\Component\HttpFoundation\Request;

/**
 * Shared helper for controllers reading a JSON request body.
 */
trait JsonRequestTrait {

  /**
   * Decodes the request body as JSON, or [] if it's empty/invalid.
   *
   * @return array<string, mixed>
   */
  protected function getJsonData(Request $request): array {
    $data = json_decode($request->getContent(), true);
    return $data ?? [];
  }
}
