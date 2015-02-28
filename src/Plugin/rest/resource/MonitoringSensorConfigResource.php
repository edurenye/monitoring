<?php

/**
 * @file
 * Definition of Drupal\monitoring\Plugin\rest\resource\MonitoringSensorResource.
 */

namespace Drupal\monitoring\Plugin\rest\resource;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\monitoring\Sensor\NonExistingSensorException;
use Drupal\monitoring\Sensor\SensorManager;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;
use Psr\Log\LoggerInterface;

/**
 * Provides a resource for monitoring sensors.
 *
 * @RestResource(
 *   id = "monitoring-sensor",
 *   label = @Translation("Monitoring sensor config")
 * )
 */
class MonitoringSensorConfigResource extends ResourceBase {

  /**
   * The sensor manager.
   *
   * @var \Drupal\monitoring\Sensor\SensorManager
   */
  protected $sensorManager;

  public function __construct(array $configuration, $plugin_id, array $plugin_definition, array $serializer_formats, SensorManager $sensor_manager, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->sensorManager = $sensor_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('monitoring.sensor_manager'),
      $container->get('logger.factory')->get('ajax')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $path_prefix = strtr($this->pluginId, ':', '/');
    $route_name = strtr($this->pluginId, ':', '.');

    $collection = parent::routes();
    $route = new Route("/$path_prefix", array(
      '_controller' => 'Drupal\rest\RequestHandler::handle',
      // Pass the resource plugin ID along as default property.
      '_plugin' => $this->pluginId,
    ), array(
      // The HTTP method is a requirement for this route.
      '_method' => 'GET',
      '_permission' => "restful get $this->pluginId",
    ));
    foreach ($this->serializerFormats as $format_name) {
      // Expose one route per available format.
      $format_route = clone $route;
      $format_route->addRequirements(array('_format' => $format_name));
      $collection->add("$route_name.list.$format_name", $format_route);
    }
    return $collection;
  }

  /**
   * Responds to sensor INFO GET requests.
   *
   * @param string $sensor_name
   *   (optional) The sensor name, returns a list of all sensors when empty.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the sensor config.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get($sensor_name = NULL) {
    if ($sensor_name) {
      try {
        $sensor_config = $this->sensorManager->getSensorConfigByName($sensor_name);
      }
      catch (NonExistingSensorException $e) {
        throw new NotFoundHttpException($e->getMessage(), $e);
      }
      $response = $sensor_config->getDefinition();
      $response['uri'] = \Drupal::request()->getUriForPath('/monitoring-sensor/' . $sensor_name);
      return new ResourceResponse($response);
    }

    $list = array();
    foreach ($this->sensorManager->getAllSensorConfig() as $sensor_name => $sensor_config) {
      $list[$sensor_name] = $sensor_config->getDefinition();
      $list[$sensor_name]['uri'] = \Drupal::request()->getUriForPath('/monitoring-sensor/' . $sensor_name);
    }
    return new ResourceResponse($list);
  }

}
