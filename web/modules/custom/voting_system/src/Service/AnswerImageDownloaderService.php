<?php

declare(strict_types=1);

namespace Drupal\voting_system\Service;

use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\voting_system\Exception\InvalidAnswerImageException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Psr\Http\Message\ResponseInterface;

/**
 * Downloads an externally hosted image and stores it as a managed file.
 */
class AnswerImageDownloaderService {

  private const MAX_BYTES = 5 * 1024 * 1024;

  private const MAX_REDIRECTS = 3;

  private const ALLOWED_SCHEMES = ['http', 'https'];

  private const DESTINATION_DIRECTORY = 'public://voting_system_answers';

  private const EXTENSION_BY_MIME_TYPE = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
  ];

  public function __construct(
    protected readonly ClientInterface $httpClient,
    protected readonly FileRepositoryInterface $fileRepository,
    protected readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Downloads $image_url and stores it as a permanent managed file.
   *
   * @throws \Drupal\voting_system\Exception\InvalidAnswerImageException
   */
  public function download(string $image_url): FileInterface {
    $response = $this->fetch($image_url);

    $content_length = (int) $response->getHeaderLine('Content-Length');
    if ($content_length > self::MAX_BYTES) {
      throw new InvalidAnswerImageException('The image exceeds the 5MB size limit.');
    }

    $body = (string) $response->getBody();
    if (strlen($body) > self::MAX_BYTES) {
      throw new InvalidAnswerImageException('The image exceeds the 5MB size limit.');
    }

    $extension = $this->resolveExtension($response->getHeaderLine('Content-Type'), $image_url);
    if (!$extension) {
      throw new InvalidAnswerImageException('The URL does not point to a supported image type (png, jpg, webp, gif).');
    }

    $directory = self::DESTINATION_DIRECTORY;
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new InvalidAnswerImageException('Could not prepare the storage directory for the image.');
    }

    $destination = $directory . '/' . bin2hex(random_bytes(8)) . '.' . $extension;

    try {
      $file = $this->fileRepository->writeData($body, $destination, FileExists::Rename);
    }
    catch (FileException $exception) {
      throw new InvalidAnswerImageException('Could not store the downloaded image.', 0, $exception);
    }

    $file->setPermanent();
    $file->save();

    return $file;
  }

  /**
   * Fetches $image_url, following redirects manually (each hop re-validated
   * by assertUrlIsSafe()) instead of letting Guzzle auto-follow them a
   * safe URL can still redirect to an internal address.
   *
   * @throws \Drupal\voting_system\Exception\InvalidAnswerImageException
   */
  private function fetch(string $image_url): ResponseInterface {
    $url = $image_url;

    for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
      $this->assertUrlIsSafe($url);

      try {
        $response = $this->httpClient->request('GET', $url, [
          'timeout' => 10,
          'allow_redirects' => FALSE,
        ]);
      }
      catch (GuzzleException $exception) {
        throw new InvalidAnswerImageException(sprintf('Could not download image from "%s".', $url), 0, $exception);
      }

      $status = $response->getStatusCode();
      if ($status < 300 || $status >= 400) {
        return $response;
      }

      $location = $response->getHeaderLine('Location');
      if ($location === '') {
        throw new InvalidAnswerImageException(sprintf('Received a redirect from "%s" without a Location header.', $url));
      }

      $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));
    }

    throw new InvalidAnswerImageException('Too many redirects while downloading the image.');
  }

  /**
   * Rejects non-http(s) URLs and URLs resolving to a private/internal IP,
   * to guard against SSRF via an admin-supplied image URL.
   *
   * @throws \Drupal\voting_system\Exception\InvalidAnswerImageException
   */
  private function assertUrlIsSafe(string $image_url): void {
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
      throw new InvalidAnswerImageException(sprintf('"%s" is not a valid URL.', $image_url));
    }

    $scheme = strtolower((string) parse_url($image_url, PHP_URL_SCHEME));
    $host = (string) parse_url($image_url, PHP_URL_HOST);

    if (!in_array($scheme, self::ALLOWED_SCHEMES, TRUE) || $host === '') {
      throw new InvalidAnswerImageException(sprintf('"%s" is not a valid URL.', $image_url));
    }

    $ips = $this->resolveHostIps($host);
    if (!$ips) {
      throw new InvalidAnswerImageException(sprintf('Could not resolve host "%s".', $host));
    }

    foreach ($ips as $ip) {
      if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        throw new InvalidAnswerImageException(sprintf('"%s" resolves to a disallowed private/internal address.', $host));
      }
    }
  }

  /**
   * Resolves a host to all its IPv4/IPv6 addresses (or itself, if already
   * an IP). Every address must be checked, since only one of several A/AAAA
   * records needs to be internal for an SSRF bypass.
   *
   * @return string[]
   */
  private function resolveHostIps(string $host): array {
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return [$host];
    }

    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if ($records === FALSE) {
      return [];
    }

    $ips = [];
    foreach ($records as $record) {
      $ips[] = $record['ip'] ?? $record['ipv6'] ?? NULL;
    }

    return array_values(array_filter($ips));
  }

  /**
   * Maps a Content-Type header (or, as fallback, the URL's path extension)
   * to a supported file extension, or NULL if neither is a supported image
   * type.
   */
  private function resolveExtension(string $content_type_header, string $image_url): ?string {
    $content_type = strtolower(trim(explode(';', $content_type_header)[0]));
    if (isset(self::EXTENSION_BY_MIME_TYPE[$content_type])) {
      return self::EXTENSION_BY_MIME_TYPE[$content_type];
    }

    $path_extension = strtolower(pathinfo(parse_url($image_url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    $extension = $path_extension === 'jpeg' ? 'jpg' : $path_extension;

    return in_array($extension, self::EXTENSION_BY_MIME_TYPE, TRUE) ? $extension : NULL;
  }

}
