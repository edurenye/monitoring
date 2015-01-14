<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringSearchAPITest.
 */

namespace Drupal\monitoring\Tests;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\search_api\Entity\Index;
use Drupal\search_api_db\Tests;

/**
 * Tests for search API sensor.
 *
 * @group monitoring
 */
class MonitoringSearchAPITest extends MonitoringUnitTestBase {

  /**
   * Disabled config schema checking temporarily until all errors are resolved.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'field',
    'menu_link',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'node',
    'entity_test',
    'text'
  );

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Install required database tables for each module.
    $this->installSchema('search_api', ['search_api_item', 'search_api_task']);
    $this->installSchema('system', ['router']);
    $this->installSchema('user', ['users_data']);

    // Install the schema for entity entity_test.
    $this->installEntitySchema('entity_test');

    // Set up the required bundles.
    $this->createEntityTestBundles();
    // Install the test search API index and server used by the test.
    $this->installConfig(['search_api_test_db']);
  }

  /**
   * Tests individual sensors.
   */
  public function testSensors() {

    // Create content first to avoid a Division by zero error.
    // Two new articles, none indexed.
    $entity = EntityTest::create(array('type' => 'article'));
    $entity->save();
    $entity = EntityTest::create(array('type' => 'article'));
    $entity->save();

    $result = $this->runSensor('search_api_database_search_index');
    $this->assertEqual($result->getValue(), 2);

    // Update the index to test sensor result.
    $index = Index::load('database_search_index');
    $index->index();

    $entity = EntityTest::create(array('type' => 'article'));
    $entity->save();
    $entity = EntityTest::create(array('type' => 'article'));
    $entity->save();
    $entity = EntityTest::create(array('type' => 'article'));
    $entity->save();

    // New articles are not yet indexed.
    $result = $this->runSensor('search_api_database_search_index');
    $this->assertEqual($result->getValue(), 3);

    $index = Index::load('database_search_index');
    $index->index();

    // Everything should be indexed.
    $result = $this->runSensor('search_api_database_search_index');
    $this->assertEqual($result->getValue(), 0);
  }

  /**
   * Sets up the necessary bundles on the test entity type.
   */
  protected function createEntityTestBundles() {
    entity_test_create_bundle('item');
    entity_test_create_bundle('article');
  }

}
