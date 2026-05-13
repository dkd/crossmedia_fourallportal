<?php

namespace Crossmedia\Fourallportal\Event\Import;

use Crossmedia\Fourallportal\Domain\Dto\Parameters;

class BeforeImportEvent
{
    public function __construct(
        public readonly string $modelName,
        public readonly string $remoteId,
        public readonly ?Parameters $parameters,
    ) {
    }
}
