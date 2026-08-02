<?php

namespace Drupal\voting_system\Service;

use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;

class TokenAuthService {

  protected TokenService $tokenService;

  public function __construct(TokenService $tokenService) {
    $this->tokenService = $tokenService;
  }

  public function getUserFromToken(Request $request): ?User {
    $auth_header = $request->headers->get('Authorization');
    if (!$auth_header || !str_starts_with($auth_header, 'Bearer ')) {
      return null;
    }

    $token = substr($auth_header, 7);
    $uid = $this->tokenService->validateToken($token);
    return $uid ? User::load($uid) : null;
  }

}
