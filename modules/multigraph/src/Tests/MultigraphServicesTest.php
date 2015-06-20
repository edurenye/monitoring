<?php
/**
 * @file
 * Contains \Drupal\monitoring_multigraph\Tests\MultigraphServicesTest.
 */

namespace Drupal\monitoring_multigraph\Tests;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\rest\Tests\RESTTestBase;

/**
 * Tests for REST services provided by Monitoring Multigraph.
 *
 * @group monitoring
 */
class MultigraphServicesTest extends RESTTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  public static $modules = array(
    'dblog',
    'hal',
    'rest',
    'monitoring',
    'monitoring_multigraph',
  );

  /**
   * User account to make REST requests.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $servicesAccount;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->defaultMimeType = 'application/json';
    $this->defaultFormat = 'json';

    // Enable REST API for monitoring resources.
    $config = $this->config('rest.settings');
    $settings = array(
      'monitoring-multigraph' => array(
        'GET' => array(
          'supported_formats' => array($this->defaultFormat),
          'supported_auth' => $this->defaultAuth,
        ),
      ),
    );
    $config->set('resources', $settings);
    $config->save();
    $this->rebuildCache();

    $this->servicesAccount = $this->drupalCreateUser(array(
      'restful get monitoring-multigraph',
    ));
  }

  /**
   * Test multigraph API calls.
   */
  public function testMultigraph() {
    $this->drupalLogin($this->servicesAccount);

    $response_data = $this->doRequest('monitoring-multigraph');
    $this->assertResponse(200);

    /** @var \Drupal\monitoring_multigraph\MultigraphInterface[] $multigraphs */
    $multigraphs = \Drupal::entityManager()
      ->getStorage('monitoring_multigraph')
      ->loadMultiple();

    // Test the list of multigraphs.
    foreach ($multigraphs as $name => $multigraph) {
      $this->assertEqual($response_data[$name]['id'], $multigraph->id());
      $this->assertEqual($response_data[$name]['label'], $multigraph->label());
      $this->assertEqual($response_data[$name]['description'], $multigraph->getDescription());
      $this->assertEqual($response_data[$name]['sensors'], $multigraph->getSensorsRaw());
      $this->assertEqual($response_data[$name]['uri'], Url::fromRoute('rest.monitoring-multigraph.GET.json' , ['id' => $multigraph->id(), '_format' => 'json'])->setAbsolute()->toString());
    }

    // Test response for non-existing multigraph.
    $name = 'multigraph_that_does_not_exist';
    $this->doRequest('monitoring-sensor/' . $name);
    $this->assertResponse(404);

    // Test the predefined multigraph.
    $name = 'watchdog_severe_entries';
    $response_data = $this->doRequest('monitoring-multigraph/' . $name);
    $this->assertResponse(200);
    $multigraph = $multigraphs[$name];
    $this->assertEqual($response_data['id'], $multigraph->id());
    $this->assertEqual($response_data['label'], $multigraph->label());
    $this->assertEqual($response_data['description'], $multigraph->getDescription());
    $this->assertEqual($response_data['sensors'], $multigraph->getSensorsRaw());
    $this->assertEqual($response_data['uri'], Url::fromRoute('rest.monitoring-multigraph.GET.json' , ['id' => $multigraph->id(), '_format' => 'json'])->setAbsolute()->toString());
  }

  /**
   * Do the request.
   *
   * @param string $action
   *   Action to perform.
   * @param array $query
   *   Path query key - value pairs.
   *
   * @return array
   *   Decoded json object.
   */
  protected function doRequest($action, $query = array()) {
    $query['_format'] = $this->defaultFormat;
    $url = $this->container->get('url_generator')->generateFromPath($action, array('absolute' => TRUE, 'query' => $query));
    $result = $this->httpRequest($url, 'GET', NULL, $this->defaultMimeType);
    return Json::decode($result);
  }

}
