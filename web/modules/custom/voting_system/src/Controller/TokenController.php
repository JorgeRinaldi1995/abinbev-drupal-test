<?php

namespace Drupal\voting_system\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\voting_system\Service\TokenService;

class TokenController extends ControllerBase {

  protected TokenService $tokenService;

  public function __construct(TokenService $tokenService) {
    $this->tokenService = $tokenService;
  }

  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container): static {
    return new static(
      $container->get('voting_system.token_service')
    );
  }

  public function getToken(Request $request): JsonResponse {
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

    $token = $this->tokenService->generateToken($user->id());

    return new JsonResponse(['access_token' => $token, 'token_type' => 'Bearer']);
  }
}
