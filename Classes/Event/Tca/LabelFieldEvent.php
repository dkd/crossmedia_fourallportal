<?php

namespace Crossmedia\Fourallportal\Event\Tca;

class LabelFieldEvent
{
    protected string $labelField;

    public function __construct(
        public readonly string $tableName,
        public readonly string $fieldName,
        public readonly array $availableColumns = []
    ) {
    }

    public function getLabelField(): string
    {
        if (!isset($this->labelField) || !in_array($this->labelField, $this->availableColumns)) {
            return $this->fieldName;
        }
        return $this->labelField;
    }

    public function setLabelField(string $labelField): void
    {
        if (!empty($labelField)) {
            $this->labelField = $labelField;
        }
    }
}
