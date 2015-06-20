<?php

/**
 * @file
 * Definition of Drupal\monitoring\Plugin\rest\resource\MonitoringSensorInfoResource.
 */

namespace Drupal\monitoring\Plugin\rest\resource;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Url;
use Drupal\monitoring\Sensor\DisabledSensorException;
use Drupal\monitoring\Sensor\NonExistingSensorException;
use Drupal\monitoring\Sensor\SensorManager;
use Drupal\monitoring\SensorRunner;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;
use Psr\Log\LoggerInterface;

/**
 * Provides a resource for monitoring sensors results.
 *
 * @RestResource(
 *   id = "monitoring-sensor-result",
 *   label = @Translation("Monitoring sensor result")
 * )
 */
class MonitoringSensorResultResource extends ResourceBase {

  /**
   * The sensor manager.
   *
   * @var \Drupal\monitoring\Sensor\SensorManager
   */
  protected $sensorManager;

  /**
   * The sensor runner.
   *
   * @var \Drupal\monitoring\SensorRunner
   */
  protected $sensorRunner;

  public function __construct(array $configuration, $plugin_id, array $plugin_definition, array $serializer_formats, SensorManager $sensor_manager, SensorRunner $sensor_runner, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->sensorManager = $sensor_manager;
    $this->sensorRunner = $sensor_runner;
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
      $container->get('monitoring.sensor_runner'),
      $container->get('logger.factory')->get('rest')
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
      '_permission' => "restful get $this->pluginId",
    ));
    $route->setMethods(['GET']);
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
    $request = \Drupal::request();

    $format = \Drupal::request()->getRequestFormat('ĵson');

    if ($sensor_name) {
      try {
        $sensor_config[$sensor_name] = $this->sensorManager->getSensorConfigByName($sensor_name);
        $result = $this->sensorRunner->runSensors($sensor_config);
        $response = $result[$sensor_name]->toArray();
        $response['uri'] = Url::fromRoute('rest.monitoring-sensor-result.GET.' . $format, ['id' => $sensor_name, '_format' => $format])->setAbsolute()->toString();
        if ($request->get('expand') == 'sensor') {
          $response['sensor'] = $result[$sensor_name]->getSensorConfig()->toArray();
        }
        return new ResourceResponse($response);
      }
      catch (NonExistingSensorException $e) {
        throw new NotFoundHttpException($e->getMessage(), $e);
      }
      catch (DisabledSensorException $e) {
        throw new NotFoundHttpException($e->getMessage(), $e);
      }
    }
    else {
      $list = array();
      foreach ($this->sensorRunner->runSensors() as $sensor_name => $result) {
        $list[$sensor_name] = $result->toArray();
        $list[$sensor_name]['uri'] = Url::fromRoute('rest.monitoring-sensor-result.GET.' . $format, ['id' => $sensor_name, '_format' => $format])->setAbsolute()->toString();
        if ($request->get('expand') == 'sensor') {
          $list[$sensor_name]['sensor'] = $result->getSensorConfig()->toArray();
        }
      }
      return new ResourceResponse($list);
    }

  }

}
