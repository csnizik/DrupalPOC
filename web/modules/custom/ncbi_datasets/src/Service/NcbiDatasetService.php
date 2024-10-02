<?php

namespace Drupal\ncbi_datasets\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class NcbiDatasetService.
 */
class NcbiDatasetService {

  protected $httpClient;
  protected $configFactory;

  /**
   * NcbiDatasetService constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client to make requests.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory to manage module configurations.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
  }

  /**
   * Fetch dataset from NCBI API.
   */
  public function fetchDataset($query) {
    try {
      $response = $this->httpClient->request('GET', 'https://api.ncbi.nlm.nih.gov/datasets/v1/genome/taxon/' . $query, [
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);
      $data = json_decode($response->getBody(), TRUE);
      return $data;
    } catch (\Exception $e) {
      \Drupal::logger('ncbi_datasets')->error($e->getMessage());
      return NULL;
    }
  }
}
