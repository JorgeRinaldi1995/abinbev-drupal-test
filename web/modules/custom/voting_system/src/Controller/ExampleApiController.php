<?php

namespace Drupal\voting_system\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\user\Entity\User;
use Drupal\Core\Session\AccountInterface;

class ExampleApiController extends ControllerBase {

  public function data(Request $request) {
    return new JsonResponse(['message' => 'Protected data accessed!']);
  }

    public static function access(Request $request, AccountInterface $account) {
        $auth_header = $request->headers->get('Authorization');
        if (!$auth_header || !str_starts_with($auth_header, 'Bearer ')) {
            return AccessResult::forbidden('Missing or invalid Authorization header.');
        }

        $token = substr($auth_header, 7);
        $tokenService = \Drupal::service('voting_system.token_service');
        $uid = $tokenService->validateToken($token);

        if (!$uid) {
            return AccessResult::forbidden('Invalid or expired token.');
        }

        return AccessResult::allowed()->addCacheableDependency($account);
    }
}
