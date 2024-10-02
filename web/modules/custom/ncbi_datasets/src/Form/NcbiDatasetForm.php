<?php

namespace Drupal\ncbi_datasets\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\Entity\Node;
use Drupal\ncbi_datasets\Service\NcbiDatasetService;

/**
 * Class NcbiDatasetForm.
 */
class NcbiDatasetForm extends FormBase {

  /**
   * The NCBI dataset service.
   *
   * @var \Drupal\ncbi_datasets\Service\NcbiDatasetService
   */
  protected $datasetService;

  /**
   * Constructs a new NcbiDatasetForm object.
   *
   * @param \Drupal\ncbi_datasets\Service\NcbiDatasetService $dataset_service
   *   The dataset service.
   */
  public function __construct(NcbiDatasetService $dataset_service) {
    $this->datasetService = $dataset_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ncbi_datasets.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ncbi_dataset_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Search input field.
    $form['search_query'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search for Dataset'),
      '#description' => $this->t('Enter a taxonomy name to search datasets from NCBI.'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('search_query', ''),
    ];

    // Search submit button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
    ];

    // If results exist, display the table with checkboxes.
    if ($form_state->get('dataset_rows')) {
      $form['assembly_rows'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Select'),
          $this->t('Assembly Accession'),
          $this->t('Display Name'),
          $this->t('Submitter'),
          $this->t('Submission Date'),
          $this->t('Chromosome Count'),
          $this->t('Contig N50'),
          $this->t('Estimated Size'),
          $this->t('BLAST URL'),
        ],
      ];

      // Iterate through dataset rows and add them to the table.
      foreach ($form_state->get('dataset_rows') as $key => $row) {
        $form['assembly_rows'][$key] = [
          'select' => [
            '#type' => 'checkbox',
            '#return_value' => 1,  // Return value for checked checkboxes.
            '#default_value' => 0,  // Default to unchecked.
          ],
          'assembly_accession' => [
            '#markup' => $row['assembly_accession'],
          ],
          'display_name' => [
            '#markup' => $row['display_name'],
          ],
          'submitter' => [
            '#markup' => $row['submitter'],
          ],
          'submission_date' => [
            '#markup' => $row['submission_date'],
          ],
          'chromosome_count' => [
            '#markup' => $row['chromosome_count'],
          ],
          'contig_n50' => [
            '#markup' => $row['contig_n50'],
          ],
          'estimated_size' => [
            '#markup' => $row['estimated_size'],
          ],
          'blast_url' => [
            '#markup' => '<a href="' . htmlspecialchars($row['blast_url']) . '" target="_blank">BLAST Link</a>',
          ],
        ];
      }

      // Add the button to create nodes.
      $form['create_nodes'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Nodes'),
        '#name' => 'create_nodes',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // If the "Create Nodes" button was clicked.
    if ($form_state->getTriggeringElement()['#name'] == 'create_nodes') {
      // Get selected rows (those with checkboxes checked).
      $selected_rows = array_filter($form_state->getValue('assembly_rows'), function ($value) {
        return $value === 1;  // Only return rows with checked checkboxes.
      });

      if (!empty($selected_rows)) {
        // Create nodes for selected rows.
        foreach ($selected_rows as $key => $value) {
          // Get the data for the selected row.
          $data = $form_state->get('dataset_rows')[$key];

          // Create a node of type 'assembly'.
          $node = Node::create([
            'type' => 'assembly',
            'title' => $data['assembly_accession'],
            'field_assembly_accession' => $data['assembly_accession'],
            'field_blast_link' => ['uri' => $data['blast_url'], 'title' => 'BLAST Link'],
            'field_chromosome_count' => $data['chromosome_count'],
            'field_contig_n50' => $data['contig_n50'],
            'field_estimated_size' => $data['estimated_size'],
            'field_submission_date' => $data['submission_date'],
            'field_submitter' => $data['submitter'],
          ]);
          $node->save();
        }

        // Show a success message.
        \Drupal::messenger()->addMessage($this->t('Selected assembly nodes created successfully.'));
      } else {
        // Show a message if no rows were selected.
        \Drupal::messenger()->addMessage($this->t('No rows selected. Please select at least one dataset to create nodes.'), 'warning');
      }

      return;
    }

    // Handle search functionality.
    $query = $form_state->getValue('search_query');
    $results = $this->datasetService->fetchDataset($query);

    if (!empty($results) && isset($results['assemblies'])) {
      $dataset_rows = [];

      foreach ($results['assemblies'] as $key => $assemblyItem) {
        if (isset($assemblyItem['assembly'])) {
          $assembly = $assemblyItem['assembly'];

          $dataset_rows[$key] = [
            'assembly_accession' => isset($assembly['assembly_accession']) ? $assembly['assembly_accession'] : 'N/A',
            'display_name' => isset($assembly['display_name']) ? $assembly['display_name'] : 'N/A',
            'submitter' => isset($assembly['submitter']) ? $assembly['submitter'] : 'N/A',
            'submission_date' => isset($assembly['submission_date']) ? $assembly['submission_date'] : 'N/A',
            'chromosome_count' => isset($assembly['chromosomes']) ? count($assembly['chromosomes']) : 'N/A',
            'contig_n50' => isset($assembly['contig_n50']) ? $assembly['contig_n50'] : 'N/A',
            'estimated_size' => isset($assembly['estimated_size']) ? $assembly['estimated_size'] : 'N/A',
            'blast_url' => isset($assembly['blast_url']) ? $assembly['blast_url'] : 'N/A',
          ];
        }
      }

      // Store dataset rows in the form state for later access.
      $form_state->set('dataset_rows', $dataset_rows);
    } else {
      \Drupal::messenger()->addMessage($this->t('No dataset found or data structure is unexpected.'), 'error');
    }

    // Rebuild the form to display results or nodes creation.
    $form_state->setRebuild(TRUE);
  }

}
