<?php

namespace Drupal\voting_system\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;
use Drupal\Core\Controller\ControllerBase;
use Drupal\voting_system\Service\TokenService;

class TokenController extends ControllerBase {

  public function getToken(Request $request) {
    $username = $request->get('username');
    $password = $request->get('password');

    $user = user_load_by_name($username);
    if (!$user || !$user->isActive()) {
      return new JsonResponse(['error' => 'Invalid credentials'], 401);
    }

    $is_valid = \Drupal::service('password')->check($password, $user->getPassword());
    if (!$is_valid) {
      return new JsonResponse(['error' => 'Invalid credentials'], 401);
    }

    $tokenService = \Drupal::service('voting_system.token_service');
    $token = $tokenService->generateToken($user->id());

    return new JsonResponse(['access_token' => $token, 'token_type' => 'Bearer']);
  }
}
