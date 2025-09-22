<?php

namespace Crossmedia\Fourallportal\Event\Tca;

class FieldSettingsEvent
{
    public function __construct(
        public readonly string $pimFieldType,
        public readonly string $tableName,
        public readonly string $fieldName,
        protected array $tca = []
    ) {
    }

    public function getTca(): array
    {
        return $this->tca;
    }

    public function setTca(array $tca): void
    {
        if (!empty($tca)) {
            $this->tca = $tca;
        }
    }
}
