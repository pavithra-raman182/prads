<?php

namespace Drupal\cloud_calculator\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Defines CloudCalculatorController class.
 */
class CloudCalculatorController extends ControllerBase {

  public $results;

  /**
   * Get list of server instances.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Array of Server Instances
   */
  private function getServer() {
    $nodes = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'server_instances',
        'status' => 1,
      ]
    );
    return $nodes;
  }

  /**
   * Display the markup.
   *
   * @return array
   *   Return markup array.
   */
  public function content() {
    $header = [
      'model',
      'vCPU',
      'Memory/RAM',
      ' PHP process capacity per server',
      'vCPU per process',
    ];
    foreach ($this->getServer() as $server) {
      $php_process = file_get_contents("https://questionnaire.acquia.com/PhpConfigCalculator/" . "?total_mem=" . intval($server->get('field_memory')->value) * 1000 . "&memory_limit=128&docroots=1&apc_shm=96&memcache_mem=64&shared_apc=TRUE&has_mysql=FALSE");
      $rows[] = [
        $server->get('title')->value,
        $server->get('field_vcpu')->value,
        $server->get('field_memory')->value . 'GB',
        $php_process,
        round($server->get('field_vcpu')->value / $php_process, 2),
      ];

    }
    $markup = '<table><tr>';
    foreach ($header as $item) {
      $markup .= '<th>' . $item . '</th>';
    }
    $markup .= '</tr><tbody>';

    foreach ($rows as $row) {
      $markup .= '<tr>';
      foreach ($row as $item) {
        $markup .= '<td>' . $item . '</td>';
      }
      $markup .= '</tr>';
    }

    $markup .= '</tbody></table>';
    $markup .= '<p><i>' . ('Note: vCPU per process affects PHP process performance. For better performance choose a server with higher vCPU') . '</i></p>';
    return [
      '#type' => 'markup',
      '#markup' => '<h1>' . ('Server PHP capacity model :') . '</h1>' . $markup,
    ];
  }

  /**
   * CloudCalculatorController constructor.
   */
  public function __construct() {

  }

  /**
   * Get recommendation results.
   *
   * @param array $requirements
   *   List of requirements from Cloud Calculator Form.
   *
   * @return mixed
   *   List of recommendations
   */
  public function getResults(array $requirements) {
    $this->webRecommendation($requirements);
    $this->dbRecommendation($requirements);
    $this->addonRecommendation($requirements);
    return $this->results;
  }

  /**
   * Get number of maximum requests per day capacity.
   *
   * @param array $requirements
   *   List of requirements from Cloud Calculator Form.
   *
   * @return int
   *   Number of days
   */
  public function maxRequestsPerDay(array $requirements) {

    $requests_per_day = ceil($requirements['monthly_pageviews'] * $requirements['cached_requests'] / 100 / $requirements['traffic_days']);
    $this->results['requests_per_day'] = ('Number of max pageviews per day: ') . $requests_per_day;
    return $requests_per_day;
  }

  /**
   * Get minimum php processes required.
   *
   * @param array $requirements
   *   List of requirements from Cloud Calculator Form.
   *
   * @return float
   *   Return minimum number of php processes
   */
  public function minPhpProcesses(array $requirements) {
    $php_processes_per_second = ceil($this->maxRequestsPerDay($requirements) / 3600);
    $this->results['php_processes_per_second'] = ('Number of min php processes required per second: ') . $php_processes_per_second;
    return $php_processes_per_second;
  }

  /**
   * Get PHP memory consumption.
   *
   * @param array $requirements
   *   List of requirements from Cloud Calculator Form.
   *
   * @return float
   *   Return PHP memory consumption
   */
  public function phpMemoryConsumption(array $requirements) {
    $php_memory_consumption = ceil($this->minPhpProcesses($requirements) * $requirements['php_per_process'] / 1000);
    $this->results['php_memory_consumption'] = ('Min PHP memory consumption: ') . $php_memory_consumption . 'MB';
    return $php_memory_consumption;
  }

  /**
   * Return if DB recommendation is required.
   *
   * @param array $requirements
   *   List of requirements from Cloud Calculator Form.
   *
   * @return bool
   *   Return if DB recommendation is required
   */
  public function dbRecommendation(array $requirements) {
    if ($requirements['monthly_pageviews'] <= 10000000) {
      $db_recommendation = 'c5.xlarge';
    }
    elseif ($requirements['monthly_pageviews'] > 10000000 && $requirements['monthly_pageviews'] <= 15000000) {
      $db_recommendation = 'c5.2xlarge';
    }
    elseif ($requirements['monthly_pageviews'] > 15000000 && $requirements['monthly_pageviews'] <= 30000000) {
      $db_recommendation = 'c5.4xlarge';
    }
    elseif ($requirements['monthly_pageviews'] > 30000000) {
      $db_recommendation = ('Contact Cloud Engineering');
    }

    $this->results['db_recommendation'] = ('Dedicated DB server recommendation: 2x') . $db_recommendation;
    return TRUE;
  }

  /**
   * Get list of web server recommendations.
   *
   * @param array $requirements
   *   List of requirements from Cloud Calculator Form.
   *
   * @return array
   *   List of web server recommendations
   */
  public function webRecommendation(array $requirements) {
    $this->phpMemoryConsumption($requirements);
    $servers = $this->getServer();
    foreach ($servers as $server) {

      $request_string = "?total_mem=" . intval($server->get('field_memory')->value) * 1000 . "&memory_limit=" . $requirements['php_per_process'] . "&docroots=1&apc_shm=96&memcache_mem=" . $requirements['memcache_memory'] . "&shared_apc=TRUE&has_mysql=FALSE";
      $php_process = file_get_contents("https://questionnaire.acquia.com/PhpConfigCalculator/" . $request_string);
      if ($php_process * 2 > $this->minPhpProcesses($requirements)) {
        $web_recommendation = $server->get('title')->value;
        $web_recommendation .= (', vCPU per Process: ') . round($server->get('field_vcpu')->value / $php_process, 2);
        $web_recommendation .= (', Total PHP Memory: ') . round($server->get('field_memory')->value * 2, 0);
        $web_recommendation .= (', Total PHP Processes: ') . ($php_process * 2);
        $this->results['web_recommendation'] = ('Dedicated Web server recommendation: 2x') . $web_recommendation;
        break;
      }
      elseif (floor($this->minPhpProcesses($requirements) / $php_process) <= 4) {
        $web_recommendation = $server->get('title')->value;
        $web_recommendation .= (', vCPU per Process: ') . round($server->get('field_vcpu')->value / $php_process, 2);
        $web_recommendation .= (', Total PHP Memory: ') . round($server->get('field_memory')->value * 4, 0);
        $web_recommendation .= (', Total PHP Processes: ') . ($php_process * 4);
        $this->results['web_recommendation'] = ('Dedicated Web server recommendation: 4x') . $web_recommendation;
        break;
      }
    }
    // http://questionnaire.acquia.com/PhpConfigCalculator/?total_mem=4000&memory_limit=128&docroots=1&apc_shm=96&memcache_mem=64&shared_apc=TRUE&has_mysql=TRUE
    return TRUE;
  }

  /**
   * Generate addon recommendations.
   *
   * @param array $requirements
   *   List of requirements from Cloud Calculator Form.
   *
   * @return array
   *   array of add on recommendations
   */
  public function addonRecommendation(array $requirements) {
    $addon = [];

    foreach ($requirements['compliance'] as $value) {
      if ($value != '0') {
        switch ($value) {
          case 'PCI':
            $addon[] = 'PCI VPC';
            $this->results['lb_recommendation'] = ('Dedicated Load Balancers: minimum 2xc5.large');
            $this->results['fs_recommendation'] = ('Dedicated File Server: minimum 2xc5.large');
            break;

          case 'HIPAA':
            $addon[] = 'HIPAA VPC';
            break;

          case 'PII':
          case 'FedRAMP':
          case 'FERPA':
            $addon[] = $value . ('Security Package');
            break;

        }
      }

    }
    foreach ($requirements['addons'] as $value) {
      if ($value != '0') {
        switch ($value) {
          case 'Custom VCL':
            $addon[] = ('Acquia Cloud: Premium or Elite Subscription | Legacy: Dedicated LBs');
            break;

          case 'IP Whitelisting':
            $addon[] = ('Acquia Cloud: Premium or Elite Subscription | Security Package with lower subscription levels');
            break;

          case 'VPC Whitelisting':
          case 'Dedicated Network':
            $addon[] = ('Legacy Cloud + Acquia Shield + Dedicated LBs + Dedicated RA + Shield one time setup fee');
            break;

          case 'VPN':
            $addon[] = ('Legacy Cloud + Acquia SecureVPN + Dedicated LBs + Dedicated RA + SecureVPN one time setup fee');
            break;

          case 'IdP':
            break;

        }
      }

    }
    // http://questionnaire.acquia.com/PhpConfigCalculator/?total_mem=4000&memory_limit=128&docroots=1&apc_shm=96&memcache_mem=64&shared_apc=TRUE&has_mysql=TRUE
    $this->results['addon_requirements'] = 'Compliance & Addons: ' . implode($addon, ", ");
    return TRUE;
  }

}
