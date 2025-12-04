<?php

declare(strict_types=1);

namespace Drupal\marvin_product\Site;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\ConversionInterface;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class Collection extends \ArrayObject implements \JsonSerializable, ConversionInterface {

  /**
   * {@inheritdoc}
   */
  public function jsonSerialize(): mixed {
    return $this->getArrayCopy();
  }

  /**
   * {@inheritdoc}
   */
  public function convert(FormatterOptions $options): mixed {
    $hasToBeRows = in_array($options->getFormat(), ['table', 'csv', 'tsv']);
    if ($hasToBeRows) {
      return $this->convertToRowsOfFields();
    }

    return $this->jsonSerialize();
  }

  public function convertToRowsOfFields(): RowsOfFields {
    return new RowsOfFields($this->jsonSerialize());
  }

  /**
   * {@inheritdoc}
   */
  public function __serialize(): array {
    return $this->getArrayCopy();
  }

  /**
   * {@inheritdoc}
   */
  public function __unserialize(array $data): void {
    $this->exchangeArray($data);
  }

  public function populateDefaultValues(): static {
    foreach ($this as $id => &$info) {
      $info['id'] = $id;
      $info += ['weight' => 0];
    }

    return $this;
  }

}
