<?php

declare(strict_types=1);

namespace Drupal\voting_system\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserAuthenticationInterface;
use Drupal\voting_system\Service\TokenService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Issues bearer tokens for username/password credentials.
 */
class TokenController extends ControllerBase {

  public function __construct(
    protected readonly UserAuthenticationInterface $userAuthentication,
    protected readonly TokenService $tokenService,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('user.auth'),
      $container->get('voting_system.token_service')
    );
  }

  /**
   * POST /oauth/token — expects `application/x-www-form-urlencoded`
   * `username`/`password`, not JSON.
   */
  public function getToken(Request $request): JsonResponse {
    $username = (string) $request->get('username');
    $password = (string) $request->get('password');

    $user = $this->userAuthentication->lookupAccount($username);
    if (!$user || !$user->isActive() || !$this->userAuthentication->authenticateAccount($user, $password)) {
      return new JsonResponse(['error' => 'Invalid credentials'], 401);
    }

    $token = $this->tokenService->generateToken((int) $user->id());

    return new JsonResponse(['access_token' => $token, 'token_type' => 'Bearer']);
  }

}
