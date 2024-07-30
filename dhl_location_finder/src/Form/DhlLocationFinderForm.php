<?php

namespace Drupal\dhl_location_finder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DhlLocationFinderForm extends FormBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * DhlLocationFinderForm constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dhl_location_finder_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#required' => TRUE,
    ];

    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
    ];

    $form['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Find Locations'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $country = $form_state->getValue('country');
    $city = $form_state->getValue('city');
    $postal_code = $form_state->getValue('postal_code');

    try {
      $locations = $this->findLocations($country, $city, $postal_code);
      $filtered_locations = $this->filterLocations($locations);
      $yaml_output = $this->formatToYaml($filtered_locations);
      $this->outputYaml($yaml_output);
    }
    catch (RequestException $e) {
      $this->messenger()->addError($this->t('Failed to fetch locations from DHL API. Please try again later.'));
      \Drupal::logger('dhl_location_finder')->error($e->getMessage());
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An unexpected error occurred. Please try again later.'));
      \Drupal::logger('dhl_location_finder')->error($e->getMessage());
    }
  }

  /**
   * Finds locations from DHL API.
   *
   * @param string $country
   *   The country.
   * @param string $city
   *   The city.
   * @param string $postal_code
   *   The postal code.
   *
   * @return array
   *   The locations.
   */
  private function findLocations($country, $city, $postal_code) {
    $url = 'https://api.dhl.com/location-finder';
    try {
      $response = $this->httpClient->get($url, [
        'headers' => [
          'DHL-API-Key' => 'demo-key',
        ],
        'query' => [
          'countryCode' => $country,
          'addressLocality' => $city,
          'postalCode' => $postal_code,
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);
      return $data['locations'];
    }
    catch (RequestException $e) {
      \Drupal::logger('dhl_location_finder')->error($e->getMessage());
      throw $e;
    }
  }

  /**
   * Filters locations based on specified criteria.
   *
   * @param array $locations
   *   The locations.
   *
   * @return array
   *   The filtered locations.
   */
  private function filterLocations(array $locations) {
    return array_filter($locations, function($location) {
      $address_number = filter_var($location['address']['streetAddress'], FILTER_SANITIZE_NUMBER_INT);
      $is_even_number = ($address_number % 2 === 0);
      $is_open_weekends = !empty($location['openingHours']['saturday']) && !empty($location['openingHours']['sunday']);

      return $is_even_number && $is_open_weekends;
    });
  }

  /**
   * Formats locations to YAML.
   *
   * @param array $locations
   *   The locations.
   *
   * @return array
   *   The YAML formatted locations.
   */
  private function formatToYaml(array $locations) {
    $yaml = [];
    foreach ($locations as $location) {
      $yaml[] = [
        'locationName' => $location['locationName'],
        'address' => [
          'countryCode' => $location['address']['countryCode'],
          'postalCode' => $location['address']['postalCode'],
          'addressLocality' => $location['address']['addressLocality'],
          'streetAddress' => $location['address']['streetAddress'],
        ],
        'openingHours' => $location['openingHours'],
      ];
    }
    return $yaml;
  }

  /**
   * Outputs YAML formatted data.
   *
   * @param array $yaml_output
   *   The YAML formatted data.
   */
  private function outputYaml(array $yaml_output) {
    foreach ($yaml_output as $yaml) {
      $this->messenger()->addStatus(yaml_emit($yaml));
    }
  }

}