<?php

/**
 * @file
 * Contains Drupal\monitoring_multigraph\Plugin\rest\resource\MonitoringMultigraphResource.
 */

namespace Drupal\monitoring_multigraph\Plugin\rest\resource;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\monitoring_multigraph\Entity\Multigraph;

/**
 * Provides a resource for monitoring multigraphs.
 *
 * @RestResource(
 *   id = "monitoring-multigraph",
 *   label = @Translation("Monitoring multigraph")
 * )
 */
class MonitoringMultigraphResource extends ResourceBase {

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
    ), array(
      '_access_mode' => 'ANY',
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
   * Responds to multigraph GET requests.
   *
   * @param string $multigraph_name
   *   (optional) The multigraph name, returns a list of all multigraphs when
   *   empty.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the multigraph.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get($multigraph_name = NULL) {
    if ($multigraph_name) {
      /** @var \Drupal\monitoring_multigraph\Entity\Multigraph $multigraph */
      $multigraph = \Drupal::entityManager()
        ->getStorage('monitoring_multigraph')
        ->load($multigraph_name);
      if ($multigraph == NULL) {
        throw new NotFoundHttpException('No multigraph with name "' . $multigraph_name . '"');
      }
      $response = $multigraph->getDefinition();
      $response['uri'] = \Drupal::request()->getUriForPath('/monitoring-multigraph/' . $multigraph_name);
      return new ResourceResponse($response);
    }

    $list = array();
    $multigraphs = \Drupal::entityManager()
      ->getStorage('monitoring_multigraph')
      ->loadMultiple();
    foreach ($multigraphs as $name => $multigraph) {
      /** @var \Drupal\monitoring_multigraph\Entity\Multigraph $multigraph */
      $list[$name] = $multigraph->getDefinition();
      $list[$name]['uri'] = \Drupal::request()->getUriForPath('/monitoring-multigraph/' . $name);
    }
    return new ResourceResponse($list);
  }
}
