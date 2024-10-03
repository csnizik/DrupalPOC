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
      $total_results = count($form_state->get('dataset_rows'));
      $form['results_count'] = [
        '#markup' => $this->t('Displaying @count results', ['@count' => $total_results]),
      ];
      $form['assembly_rows'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Select'),
          $this->t('Assembly'),
          $this->t('GenBank'),
          $this->t('RefSeq'),
          $this->t('Scientific Name'),
          $this->t('Modifier'),
          $this->t('Level'),
          $this->t('Release Date')
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
    'display_name' => [
      '#markup' => $row['display_name'],
    ],
    'assembly_accession' => [
      '#markup' => $row['assembly_accession'],
    ],
    'paired_assembly_accession' => [
      '#markup' => $row['paired_assembly_accession'],
    ],
    'scientific_name' => [
      '#markup' => $row['scientific_name'],
    ],
    'modifier' => [
      '#markup' => $row['modifier'],
    ],
    'assembly_level' => [
      '#markup' => $row['assembly_level'],
    ],
    'submission_date' => [
      '#markup' => $row['submission_date'],
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
    $selected_rows = array_filter($form_state->getValue('assembly_rows'), function ($row) {
      return isset($row['select']) && $row['select'] == 1;  // Only return rows with checked checkboxes.
    });

      if (!empty($selected_rows)) {
        $total_selected = count($selected_rows);
        $create_biosamples = TRUE;
        if ($total_selected > 2) {
          \Drupal::messenger()->addMessage($this->t('You selected more than 2 rows. Biosamples will not be created.'));
          $create_biosamples = FALSE;
        }
        // Create nodes for selected rows.
        foreach ($selected_rows as $key => $value) {
          // Get the data for the selected row.
          $data = $form_state->get('dataset_rows')[$key];

          // Create a node of type 'assembly'.
          $node = Node::create([
            'type' => 'assembly',
            'title' => $data['display_name'],
            'field_assembly_accession' => $data['assembly_accession'],
            'field_blast_link' => ['uri' => $data['blast_url'], 'title' => 'BLAST Link'],
            'field_chromosome_count' => $data['chromosome_count'],
            'field_contig_n50' => $data['contig_n50'],
            'field_estimated_size' => $data['estimated_size'],
            'field_submission_date' => $data['submission_date'],
            'field_submitter' => $data['submitter'],
            'field_genbank' => $data['paired_assembly_accession'],
            'field_scientific_name' => $data['scientific_name'],
            'field_modifier' => $data['modifier'],
            'field_level' => $data['assembly_level'],
            'field_release_date' => $data['submission_date']
          ]);
          $node->save();
        }
        // if ($create_biosamples == TRUE) {
        //   foreach ($data['biosample'] as $key => $biosampleItem) {
        //     dpm(['key' => $key, 'value' => $biosampleItem]);
        //     // We could create nodes of type biosample here but it could get too expensive.
        //   }
        // }
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
              'display_name' => isset($assembly['display_name']) ? $assembly['display_name'] : 'N/A',
              'assembly_accession' => isset($assembly['assembly_accession']) ? $assembly['assembly_accession'] : 'N/A',
              'paired_assembly_accession' => isset($assembly['paired_assembly_accession']) ? $assembly['paired_assembly_accession'] : 'N/A',
              'scientific_name' => isset($assembly['org']['sci_name']) ? $assembly['org']['sci_name'] : 'N/A',
              'chromosome_count' => isset($assembly['chromosomes']) ? count($assembly['chromosomes']) : 'N/A',
              'contig_n50' => isset($assembly['contig_n50']) ? $assembly['contig_n50'] : 'N/A',
              'submitter' => isset($assembly['submitter']) ? $assembly['submitter'] : 'N/A',
              'estimated_size' => isset($assembly['estimated_size']) ? $assembly['estimated_size'] : 'N/A',
              'blast_url' => isset($assembly['blast_url']) ? $assembly['blast_url'] : 'N/A',
              'modifier' => isset($assembly['biosample']['attributes'][0]) ? $assembly['biosample']['attributes'][0]['value'] . " (" . $assembly['biosample']['attributes'][0]['name'] . ")" : 'N/A',
              'scientific_name' => isset($assembly['org']['sci_name']) ? $assembly['org']['sci_name'] : 'N/A',
              'assembly_level' => isset($assembly['assembly_level']) ? $assembly['assembly_level'] : 'N/A',
              'submission_date' => isset($assembly['submission_date']) ? $assembly['submission_date'] : 'N/A'
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
