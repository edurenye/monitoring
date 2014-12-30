<?php
/**
 * @file
 * Set up dependencies for demo and tests.
 */
use Drupal\Component\Utility\Random;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Language\Language;

/**
 * Creates search_api default index.
 *
 * This is copied from search_api_install() where the code will not run in case
 * of active batch process which is the case of running tests.
 *
 * @see search_api_install()
 */
function monitoring_setup_search_api() {

  global $databases;

  $database = NULL;
  foreach ($databases as $key => $targets) {
    foreach ($targets as $target => $info) {
      $database = "$key:$target";
      break;
    }
  }

  /** @var SearchAPIServer $server */
  $server = entity_create('search_api_server', array(
    'name' => 'test_search_server',
    'machine_name' => 'test_search_server',
    'class' => 'search_api_db_service',
  ));
  $server->options['database'] = $database;
  $server->options['min_chars'] = 3;
  $server->save();

  $name = 'Monitoring test index';
  $values = array(
    'name' => $name,
    'machine_name' => preg_replace('/[^a-z0-9]+/', '_', Unicode::strtolower($name)),
    'description' => 'An automatically created search index for indexing node data. Might be configured to specific needs.',
    'server' => $server->machine_name,
    'enabled' => TRUE,
    'item_type' => 'node',
    'options' => array(
      'index_directly' => 1,
      'cron_limit' => '50',
      'data_alter_callbacks' => array(
        'search_api_alter_node_access' => array(
          'status' => 1,
          'weight' => '0',
          'settings' => array(),
        ),
      ),
      'processors' => array(
        'search_api_case_ignore' => array(
          'status' => 1,
          'weight' => '0',
          'settings' => array(
            'strings' => 0,
          ),
        ),
        'search_api_html_filter' => array(
          'status' => 1,
          'weight' => '10',
          'settings' => array(
            'title' => 0,
            'alt' => 1,
            'tags' => "h1 = 5\n" .
                "h2 = 3\n" .
                "h3 = 2\n" .
                "strong = 2\n" .
                "b = 2\n" .
                "em = 1.5\n" .
                "u = 1.5",
          ),
        ),
        'search_api_tokenizer' => array(
          'status' => 1,
          'weight' => '20',
          'settings' => array(
            'spaces' => '[^\\p{L}\\p{N}]',
            'ignorable' => '[-]',
          ),
        ),
      ),
      'fields' => array(
        'type' => array(
          'type' => 'string',
        ),
        'title' => array(
          'type' => 'text',
          'boost' => '5.0',
        ),
        'promote' => array(
          'type' => 'boolean',
        ),
        'sticky' => array(
          'type' => 'boolean',
        ),
        'created' => array(
          'type' => 'date',
        ),
        'changed' => array(
          'type' => 'date',
        ),
        'author' => array(
          'type' => 'integer',
          'entity_type' => 'user',
        ),
        'comment_count' => array(
          'type' => 'integer',
        ),
        'search_api_language' => array(
          'type' => 'string',
        ),
        'body:value' => array(
          'type' => 'text',
        ),
      ),
    ),
  );

  search_api_index_insert($values);
}
