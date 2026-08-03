<?php

declare(strict_types=1);

namespace Drupal\voting_system\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\voting_system\Service\TokenAuthService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authenticates API requests carrying a `voting_system` bearer token.
 *
 * A real authentication provider is required so Drupal's current user
 * (\Drupal::currentUser(), ControllerBase::currentUser()) reflects the
 * token's owner for the whole request — an access check alone (like
 * VotingApiAccessCheck) only gates the request, it never authenticates it.
 */
class TokenAuthenticationProvider implements AuthenticationProviderInterface {

  public function __construct(
    protected readonly TokenAuthService $tokenAuthService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request): bool {
    return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request): ?AccountInterface {
    return $this->tokenAuthService->getUserFromToken($request);
  }

}
