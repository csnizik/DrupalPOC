<?php

namespace Drupal\ncbi_datasets\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides an NCBI Dataset Block.
 *
 * @Block(
 *   id = "ncbi_dataset_block",
 *   admin_label = @Translation("NCBI Dataset Block"),
 * )
 */
class NcbiDatasetBlock extends BlockBase {

  public function build() {
    $build = [];
    $build['#markup'] = $this->t('Display dataset results here.');
    return $build;
  }

}
