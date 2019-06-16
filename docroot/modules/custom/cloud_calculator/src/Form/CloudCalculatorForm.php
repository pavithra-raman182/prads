<?php


namespace Drupal\cloud_calculator\Form;

use Drupal\cloud_calculator\Controller\CloudCalculatorController;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class CloudCalculatorForm extends FormBase {

    /**
     * Returns a unique string identifying the form.
     *
     * The returned ID should be a unique string that can be a valid PHP function
     * name, since it's used in hook implementation names such as
     * hook_form_FORM_ID_alter().
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId() {
        return 'cloud_calculator_form';
    }

    /**
     * Form constructor.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     *
     * @return array
     *   The form structure.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        $form['cloud_requirements'] = array(
            '#type' => 'details',
            '#title' => $this
                ->t('Cloud Requirements'),
            '#open' => ($form_state->isSubmitted()? FALSE:TRUE),
        );

        $form['cloud_requirements']['description'] = [
            '#type' => 'item',
            '#markup' => $this->t('Please enter application requirements.'),
        ];


        $form['cloud_requirements']['title'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Application name'),
            '#description' => $this->t('Enter the title of the project/application.'),
            '#required' => TRUE,
        ];

        $form['cloud_requirements']['traffic'] = array(
            '#type' => 'fieldset',
            '#title' => t('Traffic Stats'),
            '#attributes' => array(
                'class' => array(
                    'fieldset-no-legend',
                ),
            ),
        );

        $form['cloud_requirements']['traffic']['monthly_pageviews'] = [
            '#type' => 'number',
            '#title' => $this->t('Monthly Pageviews'),
            '#description' => $this->t('Enter maximum number of pageviews per month. Note: this has to be a number.'),
            '#required' => TRUE,
        ];

        $form['cloud_requirements']['traffic']['cached_requests'] = [
            '#type' => 'select',
            '#title' => $this
                ->t('%age cached requests'),
            '#options' => [
                '10' => $this
                    ->t('10%'),
                '20' => $this
                    ->t('20%'),
                '30' => $this
                    ->t('30%'),
                '40' => $this
                    ->t('40%'),
                '50' => $this
                    ->t('50%'),
                '60' => $this
                    ->t('60%'),
                '70' => $this
                    ->t('70%'),
                '80' => $this
                    ->t('80%'),
                '90' => $this
                    ->t('90%'),
                '100' => $this
                    ->t('100%'),
            ],
        ];

        $form['cloud_requirements']['traffic']['traffic_days'] = [
            '#type' => 'number',
            '#title' => $this
                ->t('Avg. number of traffic high days per month'),
            '#description' => $this->t('Enter number of days in a month with high-regular traffic. Note: this has to be a number.'),
            '#required' => TRUE,
            ];

        $form['cloud_requirements']['assumptions'] = array(
            '#type' => 'fieldset',
            '#title' => t('Application assumptions'),
            '#attributes' => array(
                'class' => array(
                    'fieldset-no-legend',
                ),
            ),
        );

        $form['cloud_requirements']['assumptions']['php_per_process'] = [
            '#type' => 'select',
            '#title' => $this
                ->t('PHP Memory per Process'),
            '#options' => [
                '128' => $this
                    ->t('128MB'),
                '256' => $this
                    ->t('256MB'),
            ],
        ];
        $form['cloud_requirements']['assumptions']['memcache_memory'] = [
            '#type' => 'select',
            '#title' => $this
                ->t('Memcache Memory'),
            '#options' => [
                '64' => $this
                    ->t('64MB'),
                '128' => $this
                    ->t('128MB'),
            ],
        ];

        $form['cloud_requirements']['requirements'] = array(
            '#type' => 'fieldset',
            '#title' => t('Other requirements'),
            '#attributes' => array(
                'class' => array(
                    'fieldset-no-legend',
                ),
            ),
        );

        $form['cloud_requirements']['requirements']['compliance'] = array(
            '#type' => 'checkboxes',
            '#options' => array(
                'PCI' => $this->t('PCI'),
                'PII' => $this->t('PII'),
                'HIPAA' => $this->t('HIPAA'),
                'FedRAMP' => $this->t('FedRAMP'),
                'FERPA' => $this->t('FERPA'),

            ),
            '#title' => $this
                ->t(' Compliance requirements')
        );
        $form['cloud_requirements']['requirements']['addons'] = array(
            '#type' => 'checkboxes',
            '#options' => array(
                'Custom VCL' => $this->t('Custom VCL'),
                'IP Whitelisting' => $this->t('IP Whitelisting: Port 80/443'),
                'Dedicated Network' => $this->t('Dedicated Network'),
                'VPN' => $this->t('VPN'),
                'IdP' => $this->t('IdP login for Cloud UI'),
                'VPC Whitelisting' => $this->t('VPC Whitelisting: Port 22'),
            ),
            '#title' => $this
                ->t(' Add-on requirements')
        );



        // Group submit handlers in an actions element with a key of "actions" so
        // that it gets styled correctly, and so that other modules may add actions
        // to the form. This is not required, but is convention.
        $form['cloud_requirements']['actions'] = [
            '#type' => 'actions',
        ];

        // Add a submit button that handles the submission of the form.
        $form['cloud_requirements']['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Submit'),
        ];

        if (!empty($form_state->getValue('results'))) {
            $form['results'] = array(
                '#type' => 'details',
                '#title' => $this
                    ->t('Cloud Solution'),
                '#open' => ($form_state->isSubmitted()? TRUE:FALSE),
            );
            foreach ($form_state->getValue('results') as $type => $result) {
                $form['results'][$type] = array(
                    '#type' => 'html_tag',
                    '#tag' => 'h3',
                    '#value' => $result,
                    '#attributes' => array(
                        'class' => 'entity-meta__title',
                    ),
                );
            }
            $pass_link = \Drupal::l(t('Click Here ?'), \Drupal\Core\Url::fromRoute('cloud_calculator.results'));
            $form['results']['note'] = array(
                '#type' => 'markup',
                '#markup' => t('To view all server specs @link', array('@link' => $pass_link)),

            );
        }


        return $form;

    }

    /**
     * Validate the title and the checkbox of the form
     *
     * @param array $form
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);

        $title = $form_state->getValue('title');
        $pageviews = $form_state->getValue('monthly_pageviews');

        if (strlen($title) < 10) {
            // Set an error for the form element with a key of "title".
            $form_state->setErrorByName('title', $this->t('The title must be at least 10 characters long.'));
        }

        if (empty($pageviews) || !is_numeric($pageviews)){
            // Set an error for the form element with a key of "accept".
            $form_state->setErrorByName('monthly_pageviews', $this->t('Pageviews is required and needs to be a number.'));
        }

    }

    /**
     * Form submission handler.
     *
     * @param array $form
     *   An associative array containing the structure of the form.
     * @param \Drupal\Core\Form\FormStateInterface $form_state
     *   The current state of the form.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        // Display the results.

        // Call the Static Service Container wrapper
        // We should inject the messenger service, but its beyond the scope of this example.
        $requirements =  $form_state->getValues();
        $results = new CloudCalculatorController();
        $form_state->setValue('results', $results->getResults($requirements));

        // Redirect to home
        $form_state->setRebuild();

    }

}
