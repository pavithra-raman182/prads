<?php

namespace Drupal\cloud_calculator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;

/**
 * Defines HelloController class.
 */
class CloudCalculatorController extends ControllerBase {

    public $results;

    /**
     * @return \Drupal\Core\Entity\EntityInterface[]
     */
    private function getServer() {
        $query = \Drupal::entityQuery('node');
        $query->condition('type','server_instances');
        $query->condition('status', 1);
        $query->sort('field_memory', 'ASC');
        $nids = $query->execute();

        return  \Drupal\node\Entity\Node::loadMultiple($nids);
    }

    /**
     * Display the markup.
     *
     * @return array
     *   Return markup array.
     */
    public function content() {
        $header = array('model', 'vCPU', 'Memory/RAM', ' PHP process capacity per server', 'vCPU per process');
        foreach( $this->getServer() as $server ) {
            $php_process = file_get_contents("https://questionnaire.acquia.com/PhpConfigCalculator/" . "?total_mem=" . intval($server->get('field_memory')->value) *1000 . "&memory_limit=128&docroots=1&apc_shm=96&memcache_mem=64&shared_apc=TRUE&has_mysql=FALSE");
            $rows[] = array(
                $server->get('title')->value,
                $server->get('field_vcpu')->value,
                $server->get('field_memory')->value . t('GB'),
                $php_process,
                round($server->get('field_vcpu')->value / $php_process, 2 ),
            );

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
        $markup .= '<p><i>' . t('Note: vCPU per process affects PHP process performance. For better performance choose a server with higher vCPU' ) . '</i></p>';
        return [
            '#type' => 'markup',
            '#markup' => '<h1>' . $this->t('Server PHP capacity model :') . '</h1>' . $markup,
        ];
    }

    /**
     * CloudCalculatorController constructor.
     *
     */
    public function __construct() {



    }

    /**
     * @param array $requirements
     * @return mixed
     */
    public function getResults(array $requirements) {
        $this->web_recommendation($requirements);
        $this->db_recommendation($requirements);
        $this->addon_recommendation($requirements);
        return $this->results;
    }

    /**
     * @param array $requirements
     * @return int
     */
    public function max_requests_per_day( array $requirements ) {

        $requests_per_day = ceil($requirements['monthly_pageviews'] * $requirements['cached_requests'] / 100 / $requirements['traffic_days']);
        $this->results['requests_per_day'] = t('Number of max pageviews per day: ') . $requests_per_day;
        return $requests_per_day;
    }

    /**
     * @param array $requirements
     * @return float
     */
    public function max_php_processes( array $requirements ) {
        $php_processes_per_second = ceil($this->max_requests_per_day($requirements) / 3600);
        $this->results['php_processes_per_second'] = t('Number of min php processes required per second: ') . $php_processes_per_second;
        return $php_processes_per_second;
    }

    /**
     * @param array $requirements
     * @return float
     */
    public function php_memory_consumption( array $requirements ) {
        $php_memory_consumption = ceil($this->max_php_processes($requirements) * $requirements['php_per_process']/1000);
        $this->results['php_memory_consumption'] = t('Min PHP memory consumption: ') . $php_memory_consumption . 'MB';
        return $php_memory_consumption;
    }

    /**
     * @param array $requirements
     * @return boolean
     */
    public function db_recommendation( array $requirements ) {
        if($requirements['monthly_pageviews'] <= 10000000) {
            $db_recommendation = 'c5.xlarge';
        }
        elseif ($requirements['monthly_pageviews'] > 10000000 && $requirements['monthly_pageviews'] <= 15000000) {
            $db_recommendation = 'c5.2xlarge';
        }
        elseif ($requirements['monthly_pageviews'] > 15000000 && $requirements['monthly_pageviews'] <= 30000000) {
            $db_recommendation = 'c5.4xlarge';
        }
        elseif ($requirements['monthly_pageviews'] > 30000000) {
            $db_recommendation = t('Contact Cloud Engineering');
        }

        $this->results['db_recommendation'] = t('Dedicated DB server recommendation: 2x') . $db_recommendation;
        return TRUE;
    }

    /**
     * @param array $requirements
     * @return array
     */
    public function web_recommendation( array $requirements ) {
        $this->php_memory_consumption($requirements);
        $servers = $this->getServer();
        foreach ($servers as $model => $server) {

            $request_string = "?total_mem=" . intval($server->get('field_memory')->value) *1000 . "&memory_limit=" . $requirements['php_per_process'] . "&docroots=1&apc_shm=96&memcache_mem=" . $requirements['memcache_memory'] . "&shared_apc=TRUE&has_mysql=FALSE";
            $php_process = file_get_contents("https://questionnaire.acquia.com/PhpConfigCalculator/" . $request_string);
            if($php_process * 2 > $this->max_php_processes($requirements)) {
                $web_recommendation = $server->get('title')->value;
                $web_recommendation .= t(', vCPU per Process: ') . round($server->get('field_vcpu')->value / $php_process, 2 );
                $web_recommendation .= t(', Total PHP Memory: ') . round($server->get('field_memory')->value * 2, 0 );
                $web_recommendation .= t(', Total PHP Processes: ') . ( $php_process * 2 );
                $this->results['web_recommendation'] = t('Dedicated Web server recommendation: 2x') . $web_recommendation;
                break;
            }
            elseif ( floor($this->max_php_processes($requirements) / $php_process) <= 4) {
                $web_recommendation = $server->get('title')->value;
                $web_recommendation .= t(', vCPU per Process: ') . round($server->get('field_vcpu')->value / $php_process, 2 );
                $web_recommendation .= t(', Total PHP Memory: ') . round($server->get('field_memory')->value * 4, 0 );
                $web_recommendation .= t(', Total PHP Processes: ') . ( $php_process * 4 );
                $this->results['web_recommendation'] = t('Dedicated Web server recommendation: 4x') . $web_recommendation;
                break;
            }
        }
        //http://questionnaire.acquia.com/PhpConfigCalculator/?total_mem=4000&memory_limit=128&docroots=1&apc_shm=96&memcache_mem=64&shared_apc=TRUE&has_mysql=TRUE

        return TRUE;
    }

    /**
     * @param array $requirements
     * @return array
     */
    public function addon_recommendation( array $requirements ) {
        $addon = array();

        foreach ($requirements['compliance'] as $key => $value) {
            if($value != '0'){
                switch ($value) {
                    case 'PCI':
                        $addon[] = 'PCI VPC';
                        $this->results['lb_recommendation'] = t('Dedicated Load Balancers: minimum 2xc5.large');
                        $this->results['fs_recommendation'] = t('Dedicated File Server: minimum 2xc5.large');
                        break;
                    case 'HIPAA':
                        $addon[] = 'HIPAA VPC';
                        break;
                    case 'PII':
                    case 'FedRAMP':
                    case 'FERPA':
                        $addon[] = $value . t('Security Package');
                        break;


                }
            }

        }
        foreach ($requirements['addons'] as $key => $value) {
            if ($value != '0') {
                switch ($value) {
                    case 'Custom VCL' :
                        $addon[] =  t('Acquia Cloud: Premium or Elite Subscription | Legacy: Dedicated LBs');
                        break;
                    case 'IP Whitelisting':
                        $addon[] =  t('Acquia Cloud: Premium or Elite Subscription | Security Package with lower subscription levels');
                        break;
                    case 'VPC Whitelisting':
                    case 'Dedicated Network':
                        $addon[] = t('Legacy Cloud + Acquia Shield + Dedicated LBs + Dedicated RA + Shield one time setup fee');
                        break;
                    case 'VPN':
                        $addon[] = t('Legacy Cloud + Acquia SecureVPN + Dedicated LBs + Dedicated RA + SecureVPN one time setup fee');
                        break;
                    case 'IdP':
                        break;

                }
            }

        }
        //http://questionnaire.acquia.com/PhpConfigCalculator/?total_mem=4000&memory_limit=128&docroots=1&apc_shm=96&memcache_mem=64&shared_apc=TRUE&has_mysql=TRUE
        $this->results['addon_requirements'] = t('Compliance & Addons: ') . implode($addon, ", ");
        return TRUE;
    }

}
