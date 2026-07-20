<?php
declare(strict_types=1);
namespace OrcaPhotoShare\ExternalModule;

require_once 'vendor/autoload.php';

use Google\Exception as GoogleException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JsonException;

trait GooglePhotosUtils {

    private ?string $_album_id = null;
    private array $_guzzleClients = [];

    /*
     * https://developers.google.com/photos/library/guides/upload-media
     */
    private static string $PHOTOS_BASE_URI   = 'https://photoslibrary.googleapis.com/v1/';
    private static int    $PHOTOS_BATCH_SIZE = 50;

    /**
     * Exchanges the stored refresh token for a short-lived Bearer access token.
     *
     * @throws GoogleException
     * @throws \RuntimeException
     */
    private function getAccessToken(int|string $project_id): string
    {
        $config = $this->getModuleConfig($project_id);

        $client = new \Google_Client();
        $client->setAuthConfig([
            'client_id'     => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uris' => [ $config['redirect_uri'] ],
        ]);
        $client->setScopes(self::AUTH_SCOPE);

        $token = $client->fetchAccessTokenWithRefreshToken($config['refresh_token']);

        if (isset($token['error'])) {
            throw new \RuntimeException(
                'Failed to refresh Google access token: ' . ($token['error_description'] ?? $token['error'])
            );
        }

        return $token['access_token'];
    }

    /**
     * Returns a per-project Guzzle client pre-configured with a Bearer token.
     * The client is cached for the lifetime of the request.
     *
     * @throws GoogleException
     * @throws \RuntimeException
     */
    private function getGuzzleClient(int|string $project_id): GuzzleClient
    {
        if (!isset($this->_guzzleClients[$project_id])) {
            $this->_guzzleClients[$project_id] = new GuzzleClient([
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken($project_id),
                ],
            ]);
        }
        return $this->_guzzleClients[$project_id];
    }

    /**
     * Returns all app-created albums as [ title => id ].
     * Handles pagination automatically.
     *
     * @throws RequestException
     * @throws GoogleException
     * @throws JsonException
     * @throws GuzzleException
     */
    function getAlbums(int|string $project_id): array
    {
        $client = $this->getGuzzleClient($project_id);
        $albums = [];
        $pageToken = null;

        do {
            $query = [ 'excludeNonAppCreatedData' => 'true', 'pageSize' => 50 ];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $response = $client->get(static::$PHOTOS_BASE_URI . 'albums', [ 'query' => $query ]);
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            foreach ($body['albums'] ?? [] as $album) {
                $albums[$album['title']] = $album['id'];
            }

            $pageToken = $body['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return $albums;
    }

    /**
     * Ensures the target album exists, creating it if necessary.
     * Caches the resolved album ID for the lifetime of the request.
     *
     * @throws RequestException
     * @throws GoogleException
     * @throws JsonException
     */
    function initAlbum(int|string $project_id, string $album_name, ?string $album_id): string
    {
        if (empty($this->_album_id)) {
            if (!empty($album_id)) {
                $this->_album_id = $album_id;
            } else {
                $albums = $this->getAlbums($project_id);
                if (isset($albums[$album_name])) {
                    $this->_album_id = $albums[$album_name];
                } else {
                    $this->_album_id = $this->createAlbum($project_id, $album_name);
                }
            }
        }
        return $this->_album_id;
    }

    /**
     * Uploads raw image bytes to Google Photos and returns the upload token.
     *
     * @throws RequestException
     * @throws GoogleException
     * @throws GuzzleException
     */
    function uploadImage(int|string $project_id, string $image_data, string $file_name, string $mime_type): string
    {
        $client = $this->getGuzzleClient($project_id);

        if (empty($mime_type)) {
            $finfo     = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_buffer($finfo, $image_data);
        }

        $response = $client->post(static::$PHOTOS_BASE_URI . 'uploads', [
            'headers' => [
                'Content-Type'               => 'application/octet-stream',
                'X-Goog-Upload-Content-Type' => $mime_type,
                'X-Goog-Upload-Protocol'     => 'raw',
                'X-Goog-Upload-File-Name'    => $file_name,
            ],
            'body' => $image_data,
        ]);

        return trim((string) $response->getBody());
    }

    /**
     * Adds previously uploaded images (by token) to the target album.
     * Automatically chunks requests to respect the API batch limit of 50.
     *
     * @throws RequestException
     * @throws GoogleException
     * @throws JsonException
     */
    function addImagesToAlbum(int|string $project_id, array $upload_tokens, string $album_name, ?string $album_id): array
    {
        $client = $this->getGuzzleClient($project_id);
        $this->initAlbum($project_id, $album_name, $album_id);

        $results = [];
        foreach (array_chunk($upload_tokens, static::$PHOTOS_BATCH_SIZE) as $chunk) {
            $new_media_items = array_map(
                fn(string $token): array => [ 'simpleMediaItem' => [ 'uploadToken' => $token ] ],
                $chunk
            );

            $response = $client->post(static::$PHOTOS_BASE_URI . 'mediaItems:batchCreate', [
                'json' => [
                    'albumId'       => $this->_album_id,
                    'newMediaItems' => $new_media_items,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            foreach ($body['newMediaItemResults'] ?? [] as $item_result) {
                $results[] = $item_result;
            }
        }

        return $results;
    }

    /**
     * Creates a new Google Photos album and returns its ID.
     *
     * @throws RequestException
     * @throws GoogleException
     * @throws JsonException
     */
    function createAlbum(int|string $project_id, string $album_name): string
    {
        $client = $this->getGuzzleClient($project_id);

        $response = $client->post(static::$PHOTOS_BASE_URI . 'albums', [
            'json' => [ 'album' => [ 'title' => $album_name ] ],
        ]);

        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        return $body['id'];
    }
}