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

/**
 * Downloads an externally hosted image and stores it as a managed file.
 */
class AnswerImageDownloaderService {

  private const MAX_BYTES = 5 * 1024 * 1024;

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
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
      throw new InvalidAnswerImageException(sprintf('"%s" is not a valid URL.', $image_url));
    }

    try {
      $response = $this->httpClient->request('GET', $image_url, ['timeout' => 10]);
    }
    catch (GuzzleException $exception) {
      throw new InvalidAnswerImageException(sprintf('Could not download image from "%s".', $image_url), 0, $exception);
    }

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
