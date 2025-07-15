<?php

namespace Drupal\voting_system\Trait;

use Symfony\Component\HttpFoundation\Request;

trait JsonRequestTrait {

  protected function getJsonData(Request $request): array {
    $data = json_decode($request->getContent(), true);
    return $data ?? [];
  }
}
